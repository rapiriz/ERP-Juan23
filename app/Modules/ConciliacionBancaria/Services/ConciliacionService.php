<?php

namespace App\Modules\ConciliacionBancaria\Services;

use App\Modules\ConciliacionBancaria\Models\ConciliacionDetalle;
use App\Modules\ConciliacionBancaria\Repositories\ConciliacionRepository;
use App\Modules\Shared\Auth\G2Identity;
use Illuminate\Support\Str;

class ConciliacionService
{
    public function __construct(private ConciliacionRepository $repository, private BankReconciliationService $bank, private BankPeriodService $periods) {}

    public function listarPeriodos(?string $estado = null): array
    {
        return $this->repository->listarPeriodos($estado)->toArray();
    }

    public function buscarPeriodo(int $id): ?array
    {
        return $this->repository->buscarPeriodo($id)?->toArray();
    }

    public function crearPeriodo(array $data): array
    {
        return $this->periods->create($data['fecha_desde'], $data['fecha_hasta'], $data['id_usuario'])->toArray();
    }

    public function asegurarPeriodoAutomaticoDelMes(int $actor): array
    {
        return $this->periods->forDate(today()->toDateString(), $actor)->toArray();
    }

    public function conciliarAutomatico(int $id): ?array
    {
        $result = $this->bank->automatic($id, G2Identity::id());

        $details = array_map(function ($group) {
            $detail = $this->legacyDetail($group['id']);
            $sources = array_filter(['cobro' => $detail['id_cobro'], 'caja' => $detail['id_movimiento_caja'], 'ajuste' => $detail['id_ajuste'], 'cheque' => $detail['id_cheque']]);

            return [...$detail, 'origen' => array_key_first($sources), 'id_origen' => reset($sources)];
        }, $result['detalle_conciliados']);

        $missing = array_filter($result['para_revisar'], fn ($row) => count($row['candidatos']) === 0);
        $ambiguous = array_values(array_filter($result['para_revisar'], fn ($row) => count($row['candidatos']) > 0));

        return [...$result, 'detalle_conciliados' => $details, 'periodo' => $this->buscarPeriodo($id), 'total_pendientes_procesados' => count($result['detalle_conciliados']) + count($result['para_revisar']),
            'sin_candidato' => array_column($missing, 'id_movimiento_bancario'), 'ambiguos_requieren_revision_manual' => $ambiguous];
    }

    public function conciliarManual(int $id, array $data): ?array
    {
        $sources = [];
        foreach (['cobro' => 'id_cobro', 'caja' => 'id_movimiento_caja', 'ajuste' => 'id_ajuste', 'cheque' => 'id_cheque'] as $origin => $field) {
            if (! empty($data[$field])) {
                $sources[] = ['origen' => $origin, 'id' => (int) $data[$field]];
            }
        }
        abort_unless(count($sources) === 1, 422, 'Debe indicarse exactamente un origen.');

        $group = $this->bank->reconcile([$id], $sources, (string) Str::uuid(), $data['id_usuario']);

        return $this->legacyDetail($group['id']);
    }

    private function legacyDetail(string $group): array
    {
        $detail = ConciliacionDetalle::where('id_grupo', $group)->firstOrFail();

        return [...$detail->toArray(), 'grupo_id' => $group];
    }

    public function cerrarPeriodo(int $id): ?array
    {
        return $this->bank->close($id, G2Identity::id());
    }
}
