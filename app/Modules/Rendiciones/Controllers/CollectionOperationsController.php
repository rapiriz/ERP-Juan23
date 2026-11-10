<?php

namespace App\Modules\Rendiciones\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Login\Models\User;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Rendiciones\Services\CollectionSettlementService;
use App\Modules\Rendiciones\Services\RenditionReviewService;
use App\Modules\Shared\Auth\G2Identity;
use App\Modules\Shared\Services\G2OperationAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionOperationsController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['estado' => 'nullable|in:pendiente,aplicado,rechazado,anulado,todos', 'cliente_id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1']);
        $state = $data['estado'] ?? 'pendiente';
        $items = RendicionCobro::with('rendicion')->when($state !== 'todos', fn ($q) => $q->where('estado_revision', $state))
            ->when(isset($data['cliente_id']), fn ($q) => $q->where('id_cliente', $data['cliente_id']))->orderByDesc('id_rendicion_cobro')->paginate(20)->withQueryString();

        return response()->view('rendiciones.cobros', ['items' => $items, 'estado' => $state])->header('Cache-Control', 'no-store');
    }

    public function apply(int $id, RenditionReviewService $review)
    {
        $item = RendicionCobro::findOrFail($id);
        $review->review($item->id_rendicion, ['accion' => 'aprobar', 'id_usuario_validador' => G2Identity::id(), 'cobros_aprobados' => [$id]]);

        return back()->with('success', 'Cobro aplicado en caja y cuenta corriente.');
    }

    public function details(Request $request, int $id)
    {
        $data = $request->validate(['numero_cheque' => 'required|string|max:50', 'banco_emisor' => 'required|string|max:100', 'fecha_cobro' => 'required|date_format:Y-m-d']);

        return DB::transaction(function () use ($data, $id) {
            $item = RendicionCobro::lockForUpdate()->findOrFail($id);
            abort_unless($item->estado_revision === 'pendiente' && $item->medio_pago === 'cheque', 409, 'Sólo se completan cheques pendientes.');
            $item->datos_medio_pago = $data;
            $item->save();
            G2OperationAudit::record('rendiciones', $item->id_rendicion, 'cheque_completado', G2Identity::id(), ['cobro_id' => $id, ...$data]);

            return back()->with('success', 'Datos del cheque guardados.');
        });
    }

    public function cancel(Request $request, int $id, CollectionSettlementService $collections)
    {
        $data = $request->validate(['motivo' => 'required|string|min:3|max:500']);
        $result = $collections->cancel($id, User::findOrFail(G2Identity::id()), $data['motivo']);
        if ($request->expectsJson()) {
            return response()->json(['data' => $result]);
        }

        return back()->with('success','Cobro anulado con movimientos compensatorios.');
    }
}
