<?php

namespace App\Modules\Rendiciones\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Models\RendicionCobro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RendicionApiController extends Controller
{
    /**
     * GET /api/v1/rendiciones
     *
     * Expone el listado paginado de rendiciones con sus cobros asociados
     * para consumo API (G1 / otros módulos), sin middleware de sesión web.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['nullable', 'date'],
            'id_repartidor' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string', 'in:pendiente,aprobada,rechazada'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Rendicion::query()->with('cobros');

        if (!empty($validated['fecha'])) {
            $query->whereDate('fecha', $validated['fecha']);
        }

        if (!empty($validated['id_repartidor'])) {
            $query->where('id_repartidor', (int) $validated['id_repartidor']);
        }

        if (!empty($validated['estado'])) {
            $query->where('estado', strtolower($validated['estado']));
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $rendiciones = $query->orderBy('fecha', 'desc')
            ->orderBy('id_rendicion', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $rendiciones->getCollection()
                ->map(fn (Rendicion $rendicion) => $this->serializeRendicion($rendicion))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $rendiciones->currentPage(),
                'last_page' => $rendiciones->lastPage(),
                'per_page' => $rendiciones->perPage(),
                'total' => $rendiciones->total(),
            ],
            'links' => [
                'first' => $rendiciones->url(1),
                'last' => $rendiciones->url($rendiciones->lastPage()),
                'prev' => $rendiciones->previousPageUrl(),
                'next' => $rendiciones->nextPageUrl(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * GET /api/v1/rendiciones/{id}
     *
     * Expone el detalle de una rendición puntual con sus cobros asociados.
     */
    public function show(int $id): JsonResponse
    {
        $rendicion = Rendicion::query()->with('cobros')->find($id);

        if ($rendicion === null) {
            return response()->json([
                'status' => 'error',
                'message' => "La rendición con ID {$id} no existe en el sistema.",
            ], 404)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'data' => $this->serializeRendicion($rendicion),
        ])->header('Cache-Control', 'no-store');
    }

    private function serializeRendicion(Rendicion $rendicion): array
    {
        return [
            'id_rendicion' => $rendicion->id_rendicion,
            'id_repartidor' => $rendicion->id_repartidor,
            'id_entrega' => $rendicion->id_entrega,
            'fecha' => $rendicion->fecha?->toDateTimeString() ?? (string) $rendicion->fecha,
            'total_rendido' => number_format((float) $rendicion->total_rendido, 2, '.', ''),
            'estado' => $rendicion->estado,
            'observaciones' => $rendicion->observaciones,
            'id_usuario_validador' => $rendicion->id_usuario_validador,
            'fecha_validacion' => $rendicion->fecha_validacion?->toDateTimeString() ?? (string) $rendicion->fecha_validacion,
            'motivo_rechazo' => $rendicion->motivo_rechazo,
            'cobros' => $rendicion->cobros->map(function (RendicionCobro $cobro) {
                return [
                    'id_rendicion_cobro' => $cobro->id_rendicion_cobro,
                    'id_rendicion' => $cobro->id_rendicion,
                    'id_cliente' => $cobro->id_cliente,
                    'venta_id' => $cobro->id_factura,
                    'id_factura' => $cobro->id_factura,
                    'monto' => number_format((float) $cobro->monto, 2, '.', ''),
                    'medio_pago' => $cobro->medio_pago,
                    'fecha_registro' => $cobro->fecha_registro?->toDateTimeString() ?? (string) $cobro->fecha_registro,
                ];
            })->values()->all(),
        ];
    }
}

