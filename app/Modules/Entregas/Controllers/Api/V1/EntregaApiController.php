<?php

namespace App\Modules\Entregas\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoints stateless de lectura para Entregas y Remitos (/api/v1/entregas, /api/v1/remitos).
 */
class EntregaApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!Schema::hasTable('entrega')) {
            return $this->tablaNoDisponible('entrega');
        }

        $validated = $request->validate([
            'repartidor_id' => ['nullable', 'integer', 'min:1'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = DB::table('entrega as e')
            ->leftJoin('entrega_pedido as ep', 'e.id_entrega', '=', 'ep.id_entrega')
            ->select([
                'e.id_entrega',
                'e.id_repartidor',
                'e.fecha_creacion',
                'e.fecha_salida',
                'e.estado',
                'e.observaciones',
                DB::raw('COUNT(DISTINCT ep.id_venta) AS cantidad_pedidos'),
            ])
            ->groupBy('e.id_entrega', 'e.id_repartidor', 'e.fecha_creacion', 'e.fecha_salida', 'e.estado', 'e.observaciones');

        if (!empty($validated['repartidor_id'])) {
            $query->where('e.id_repartidor', (int) $validated['repartidor_id']);
        }
        if (!empty($validated['fecha'])) {
            $query->whereDate('e.fecha_salida', $validated['fecha']);
        }
        if (!empty($validated['estado'])) {
            $query->where('e.estado', $validated['estado']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $entregas = $query->orderByDesc('e.id_entrega')->paginate($perPage);

        $data = $entregas->getCollection()->map(function (object $row) {
            $carga = Schema::hasTable('remito') && Schema::hasTable('detalle_remito')
                ? DB::table('remito as r')
                    ->join('detalle_remito as dr', 'dr.id_remito', '=', 'r.id_remito')
                    ->where('r.id_entrega', $row->id_entrega)
                    ->selectRaw('COALESCE(SUM(dr.bultos), 0) as total_bultos, COALESCE(SUM(dr.cantidad), 0) as total_unidades')
                    ->first()
                : null;

            $bultos = (int) ($carga->total_bultos ?? 0);
            $unidades = (int) ($carga->total_unidades ?? 0);

            return [
                'id_entrega' => (int) $row->id_entrega,
                'id_repartidor' => (int) $row->id_repartidor,
                'repartidor_nombre' => UsuarioService::obtenerNombre((int) $row->id_repartidor),
                'fecha_creacion' => $row->fecha_creacion,
                'fecha_salida' => $row->fecha_salida,
                'estado' => $row->estado,
                'observaciones' => $row->observaciones,
                'cantidad_pedidos' => (int) $row->cantidad_pedidos,
                'total_bultos' => $bultos,
                'total_unidades' => $unidades,
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $entregas->currentPage(),
                'last_page' => $entregas->lastPage(),
                'per_page' => $entregas->perPage(),
                'total' => $entregas->total(),
            ],
            'links' => [
                'first' => $entregas->url(1),
                'last' => $entregas->url($entregas->lastPage()),
                'prev' => $entregas->previousPageUrl(),
                'next' => $entregas->nextPageUrl(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function show(int $id): JsonResponse
    {
        if (!Schema::hasTable('entrega')) {
            return $this->tablaNoDisponible('entrega');
        }

        $entrega = DB::table('entrega')->where('id_entrega', $id)->first();
        if ($entrega === null) {
            return response()->json([
                'status' => 'error',
                'message' => "La entrega #{$id} no existe en el sistema.",
            ], 404)->header('Cache-Control', 'no-store');
        }

        $pedidos = [];
        if (Schema::hasTable('entrega_pedido')) {
            $query = DB::table('entrega_pedido as ep')->where('ep.id_entrega', $id);
            if (Schema::hasTable('remito')) {
                $query->leftJoin('remito as r', function ($join) {
                    $join->on('r.id_entrega', '=', 'ep.id_entrega')
                        ->on('r.id_venta', '=', 'ep.id_venta');
                })->select([
                    'ep.id_entrega_pedido',
                    'ep.id_venta',
                    'ep.estado_pedido_entrega',
                    'ep.motivo_rechazo',
                    'r.id_remito',
                    'r.numero_remito',
                    'r.estado as estado_remito',
                ]);
            } else {
                $query->select([
                    'ep.id_entrega_pedido',
                    'ep.id_venta',
                    'ep.estado_pedido_entrega',
                    'ep.motivo_rechazo',
                ]);
            }

            $pedidos = $query->orderBy('ep.id_entrega_pedido')->get()->map(fn (object $p) => [
                'id_entrega_pedido' => (int) $p->id_entrega_pedido,
                'id_venta' => (int) $p->id_venta,
                'estado_pedido_entrega' => $p->estado_pedido_entrega,
                'motivo_rechazo' => $p->motivo_rechazo,
                'id_remito' => isset($p->id_remito) ? (int) $p->id_remito : null,
                'numero_remito' => $p->numero_remito ?? null,
                'estado_remito' => $p->estado_remito ?? null,
            ])->values()->all();
        }

        return response()->json([
            'data' => [
                'id_entrega' => (int) $entrega->id_entrega,
                'id_repartidor' => (int) $entrega->id_repartidor,
                'repartidor_nombre' => UsuarioService::obtenerNombre((int) $entrega->id_repartidor),
                'fecha_creacion' => $entrega->fecha_creacion,
                'fecha_salida' => $entrega->fecha_salida,
                'estado' => $entrega->estado,
                'observaciones' => $entrega->observaciones,
                'pedidos' => $pedidos,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function showRemito(int $id): JsonResponse
    {
        if (!Schema::hasTable('remito')) {
            return $this->tablaNoDisponible('remito');
        }

        $remito = DB::table('remito')->where('id_remito', $id)->first();
        if ($remito === null) {
            return response()->json([
                'status' => 'error',
                'message' => "El remito #{$id} no existe en el sistema.",
            ], 404)->header('Cache-Control', 'no-store');
        }

        $items = Schema::hasTable('detalle_remito')
            ? DB::table('detalle_remito')->where('id_remito', $id)->get()->map(fn (object $item) => [
                'id_detalle_remito' => (int) ($item->id_detalle_remito ?? 0),
                'id_producto' => isset($item->id_producto) ? (int) $item->id_producto : null,
                'descripcion_producto' => $item->descripcion_producto ?? null,
                'cantidad' => (int) ($item->cantidad ?? 0),
                'bultos' => (int) ($item->bultos ?? 0),
            ])->values()->all()
            : [];

        return response()->json([
            'data' => [
                'id_remito' => (int) $remito->id_remito,
                'numero_remito' => $remito->numero_remito,
                'id_entrega' => (int) $remito->id_entrega,
                'id_venta' => (int) $remito->id_venta,
                'fecha_emision' => $remito->fecha_emision,
                'estado' => $remito->estado,
                'observaciones' => $remito->observaciones,
                'items' => $items,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function tablaNoDisponible(string $tabla): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => "El módulo de Entregas no está disponible en esta base (falta tabla {$tabla}).",
        ], 503)->header('Cache-Control', 'no-store');
    }
}
