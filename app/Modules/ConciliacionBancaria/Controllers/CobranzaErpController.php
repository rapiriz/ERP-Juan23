<?php

namespace App\Modules\ConciliacionBancaria\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ConciliacionBancaria\Repositories\Cobro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobranzaErpController extends Controller
{
    /**
     * GET /api/cobranzas-erp
     * Lista cobranzas registradas en el ERP para el panel split-screen de conciliación.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['fecha_desde' => 'nullable|date_format:Y-m-d', 'fecha_hasta' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_desde', 'monto_min' => 'nullable|numeric|min:0', 'monto_max' => 'nullable|numeric|min:0', 'estado' => 'nullable|in:confirmado,registrado,anulado', 'cliente_id' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1']);
        $query = Cobro::query();

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->input('fecha_hasta'));
        }
        if ($request->filled('monto_min')) {
            $query->where('monto_total', '>=', (float) $request->input('monto_min'));
        }
        if ($request->filled('monto_max')) {
            $query->where('monto_total', '<=', (float) $request->input('monto_max'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado') === 'confirmado' ? 'registrado' : $request->input('estado'));
        } else {
            // Criterio de Aceptación 4.2: Filtro predeterminado estado = aprobado / confirmado
            $query->where('estado', 'registrado');
        }
        if ($request->filled('comprobante')) {
            $query->where('comprobante_nro', 'like', '%'.$request->input('comprobante').'%');
        }
        if ($request->filled('cliente_id')) {
            $query->where('id_cliente', $request->input('cliente_id'));
        }

        $perPage = (int) $request->input('per_page', 25);
        $cobranzas = $query->orderBy('fecha', 'desc')
            ->orderBy('id_cobro', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $cobranzas->items(),
            'total' => $cobranzas->total(),
            'current_page' => $cobranzas->currentPage(),
            'per_page' => $cobranzas->perPage(),
            'last_page' => $cobranzas->lastPage(),
        ]);
    }

    /**
     * GET /api/cobranzas-erp/{id}
     */
    public function show(int $id): JsonResponse
    {
        $cobro = Cobro::find($id);
        if (! $cobro) {
            return response()->json([
                'error' => true,
                'codigo' => 404,
                'mensaje' => "Cobranza ERP #{$id} no encontrada.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cobro,
        ]);
    }
}
