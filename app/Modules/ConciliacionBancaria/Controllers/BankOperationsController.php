<?php

namespace App\Modules\ConciliacionBancaria\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\ConciliacionBancaria\Models\PeriodoConciliacion;
use App\Modules\ConciliacionBancaria\Services\BankPeriodService;
use App\Modules\ConciliacionBancaria\Services\BankReconciliationService;
use App\Modules\Shared\Auth\G2Identity;
use App\Modules\Shared\Services\G2OperationAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankOperationsController extends Controller
{
    public function index(Request $request, BankReconciliationService $bank)
    {
        $filters = $request->validate(['periodo_id' => ['nullable', 'integer', 'exists:PERIODO_CONCILIACION,id_periodo']]);
        $id = $filters['periodo_id'] ?? null;
        $movements = MovimientoBancario::when($id, fn ($q) => $q->where('id_periodo', $id))->orderByDesc('fecha_operacion')->orderByDesc('id_movimiento_bancario')->get();
        abort_if($movements->count() > 2000, 422, 'Seleccioná un período para acotar la consulta.');

        return response()->json(['periodos' => PeriodoConciliacion::orderByDesc('fecha_desde')->get(), 'movimientos' => $movements,
            'origenes' => array_map(fn ($s) => collect($s)->except('aliases')->all(), $bank->sources()),
            'propuestas' => $id ? $bank->proposals((int) $id) : ['alta_confianza' => [], 'para_revisar' => []],
            'historial' => DB::table('g2_conciliacion_grupos')->when($id, fn ($q) => $q->where('id_periodo', $id))->orderByDesc('created_at')->get()])->header('Cache-Control', 'no-store');
    }

    public function reconcile(Request $request, BankReconciliationService $bank)
    {
        $data = $request->validate(['request_key' => 'required|uuid', 'movimientos' => 'required|array|min:1|max:100', 'movimientos.*' => 'integer|min:1|distinct',
            'origenes' => 'required|array|min:1|max:100', 'origenes.*.origen' => 'required|in:cobro,caja,ajuste,cheque', 'origenes.*.id' => 'required|integer|min:1', 'nota' => 'nullable|string|max:1000']);

        return response()->json(['data' => $bank->reconcile($data['movimientos'], $data['origenes'], $data['request_key'], G2Identity::id(), 'manual', $data['nota'] ?? null)], 201);
    }

    public function automatic(int $id, BankReconciliationService $bank)
    {
        return response()->json(['data' => $bank->automatic($id, G2Identity::id())]);
    }

    public function reverse(Request $request, string $id, BankReconciliationService $bank)
    {
        $data = $request->validate(['motivo' => 'required|string|min:3|max:1000']);

        return response()->json(['data' => $bank->reverse($id, G2Identity::id(), $data['motivo'])]);
    }

    public function classify(Request $request, int $id, BankReconciliationService $bank)
    {
        $data = $request->validate(['accion' => 'required|in:disputar,resolver,gasto', 'nota' => 'required|string|min:3|max:1000']);

        return response()->json(['data' => $bank->classify($id, G2Identity::id(), $data['accion'], $data['nota'])]);
    }

    public function createPeriod(Request $request, BankPeriodService $periods)
    {
        $data = $request->validate(['fecha_desde' => 'required|date_format:Y-m-d', 'fecha_hasta' => 'required|date_format:Y-m-d|after_or_equal:fecha_desde']);

        return response()->json(['data' => $periods->create($data['fecha_desde'], $data['fecha_hasta'], G2Identity::id())], 201);
    }

    public function close(int $id, BankReconciliationService $bank)
    {
        return response()->json(['data' => $bank->close($id, G2Identity::id())]);
    }

    public function assign(Request $request, int $id, BankPeriodService $periods)
    {
        $data = $request->validate(['periodo_id' => 'required|integer|min:1']);

        return DB::transaction(function () use ($id, $data, $periods) {
            $periods->actor(G2Identity::id());
            $period = $periods->open($data['periodo_id']);
            $move = MovimientoBancario::lockForUpdate()->findOrFail($id);
            abort_if($move->id_periodo !== null, 409, 'El movimiento ya pertenece a un período.');
            abort_unless($move->fecha_operacion->toDateString() >= $period->fecha_desde->toDateString() && $move->fecha_operacion->toDateString() <= $period->fecha_hasta->toDateString(), 422, 'Fecha fuera de período.');
            $move->id_periodo = $period->id_periodo;
            $move->save();
            G2OperationAudit::record('bancos',$id,'periodo_asignado',G2Identity::id(),$data);

            return response()->json(['data' => $move]);
        });
    }
}
