<?php

namespace App\Modules\ConciliacionBancaria\Services;

use App\Modules\ConciliacionBancaria\Models\Cheque;
use App\Modules\ConciliacionBancaria\Models\ConciliacionDetalle;
use App\Modules\ConciliacionBancaria\Repositories\ChequeRepository;
use App\Modules\Login\Models\User;
use App\Modules\Rendiciones\Services\CollectionSettlementService;
use App\Modules\Shared\Auth\G2Identity;
use App\Modules\Shared\Services\G2OperationAudit;
use Illuminate\Support\Facades\DB;

class ChequeService
{
    public function __construct(private ChequeRepository $repository, private BankReconciliationService $bank, private CollectionSettlementService $collections, private BankPeriodService $periods) {}

    public function listar(?string $estado = null): array
    {
        return Cheque::with('cobro')->when($estado, fn ($q) => $q->where('estado', $estado))->orderByDesc('fecha_cobro')->get()->toArray();
    }

    public function buscar(int $id): ?array
    {
        return Cheque::with('cobro')->find($id)?->toArray();
    }

    public function depositar(int $id): ?array
    {
        $actor = G2Identity::id();
        $this->periods->actor($actor);

        return DB::transaction(function () use ($id, $actor) {
            $check = Cheque::lockForUpdate()->findOrFail($id);
            if ($check->estado === 'depositado') {
                return $check->toArray();
            }
            abort_unless($check->estado === 'en_cartera', 409, 'Sólo se deposita un cheque en cartera.');
            abort_unless($check->cobro?->estado === 'registrado', 409, 'El cobro está anulado o falta.');
            $this->repository->marcarDepositado($check);
            G2OperationAudit::record('cheques', $id, 'depositado', $actor, []);

            return $check->toArray();
        });
    }

    public function rechazar(int $id, string $reason = 'Rechazo bancario informado'): ?array
    {
        $actor = User::findOrFail(G2Identity::id());

        return DB::transaction(function () use ($id, $actor, $reason) {
            $check = Cheque::lockForUpdate()->findOrFail($id);
            if ($check->estado === 'rechazado') {
                return ['cheque' => $check->toArray(), 'repetido' => true];
            }
            $reverted = false;
            foreach (ConciliacionDetalle::where('id_cheque', $id)->get() as $detail) {
                abort_unless($detail->id_grupo, 409, 'La conciliación histórica debe revisarse antes de rechazar este cheque.');
                $this->bank->reverse($detail->id_grupo, $actor->id, $reason);
                $reverted = true;
            }
            $reversal = $this->collections->cancel($check->id_cobro, $actor, $reason);
            $check->refresh();
            $this->repository->marcarRechazado($check);
            G2OperationAudit::record('cheques', $id, 'rechazado', $actor->id, ['motivo' => $reason, 'reversion' => $reversal]);

            return ['cheque' => $check->toArray(), 'conciliacion_revertida' => $reverted, 'reversion' => $reversal];
        });
    }
}
