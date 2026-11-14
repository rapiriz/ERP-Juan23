<?php

namespace App\Modules\Rendiciones\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Rendiciones\Models\RendicionCobro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobroApiController extends Controller
{
    /**
     * GET /api/v1/cobros
     *
     * Expone el historial paginado de cobros registrados en rendiciones.
     * Admite filtros opcionales por cliente_id, estado de rendición y rango de fechas.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string', 'in:pendiente,aprobada,rechazada'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = RendicionCobro::query()->with('rendicion');

        if (!empty($validated['cliente_id'])) {
            $clienteId = (int) $validated['cliente_id'];
            if (!Cliente::where('id', $clienteId)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "El cliente con ID {$clienteId} no existe en el sistema.",
                ], 404)->header('Cache-Control', 'no-store');
            }
            $query->where('id_cliente', $clienteId);
        }

        if (!empty($validated['estado'])) {
            $estado = strtolower($validated['estado']);
            $query->whereHas('rendicion', function ($q) use ($estado) {
                $q->where('estado', $estado);
            });
        }

        if (!empty($validated['desde'])) {
            $query->whereDate('fecha_registro', '>=', $validated['desde']);
        }

        if (!empty($validated['hasta'])) {
            $query->whereDate('fecha_registro', '<=', $validated['hasta']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $cobros = $query->orderBy('fecha_registro', 'desc')
            ->orderBy('id_rendicion_cobro', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $cobros->getCollection()->map(function (RendicionCobro $cobro) {
                return [
                    'id' => $cobro->id_rendicion_cobro,
                    'cliente_id' => $cobro->id_cliente,
                    'rendicion_id' => $cobro->id_rendicion,
                    'venta_id' => $cobro->id_factura,
                    'factura_id' => $cobro->id_factura,
                    'fecha' => $cobro->fecha_registro?->toDateTimeString() ?? (string) $cobro->fecha_registro,
                    'monto_total' => number_format((float) $cobro->monto, 2, '.', ''),
                    'medio_pago' => $cobro->medio_pago,
                    'comprobante_nro' => null,
                    'observaciones' => $cobro->rendicion?->observaciones,
                    'estado_rendicion' => $cobro->rendicion?->estado,
                    'confirmacion_individual' => null, // G2 no certifica liquidación/confirmación individual de cobro en banco
                ];
            })->values()->all(),
            'meta' => [
                'current_page' => $cobros->currentPage(),
                'last_page' => $cobros->lastPage(),
                'per_page' => $cobros->perPage(),
                'total' => $cobros->total(),
            ],
            'links' => [
                'first' => $cobros->url(1),
                'last' => $cobros->url($cobros->lastPage()),
                'prev' => $cobros->previousPageUrl(),
                'next' => $cobros->nextPageUrl(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}

