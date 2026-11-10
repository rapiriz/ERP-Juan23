<?php

declare(strict_types=1);

namespace App\Modules\PedidosDeCompra\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PedidosDeCompra\Models\DetalleOrden;
use App\Modules\PedidosDeCompra\Models\OrdenCompra;
use App\Modules\PedidosDeCompra\Services\PurchaseOrderProgress;
use App\Modules\Productos\Models\LogAuditoria;
use App\Modules\Productos\Models\Producto;
use App\Modules\Proveedores\Models\Proveedor;
use App\Modules\Stock\Services\ProductUnitService;
use App\Support\UsuarioActual;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Módulo Órdenes de Compra.
 *
 * PC01 - Generar órdenes de compra.
 * PC02 - Consultar órdenes de compra emitidas.
 * PC03 - Consultar el detalle de una orden de compra.
 * PC04 - Editar una orden de compra.
 * PC05 - Cancelar una orden de compra.
 * PC06 - Visualizar el estado de una orden de compra.
 */
class OrdenCompraController extends Controller
{
    /**
     * Formato de una orden (sin detalles).
     */
    private function formato(OrdenCompra $o): array
    {
        $comprasRel = $o->relationLoaded('compras')
            ? $o->compras
            : $o->compras()->with('detalles')->orderByDesc('id_compra')->get();

        $comprasActivas = $comprasRel->filter(fn ($c) => $c->estado !== 'cancelada')->values();
        $compraActiva = $comprasActivas->first() ?? $comprasRel->first();

        $detallesOrden = $o->relationLoaded('detalles') ? $o->detalles : $o->detalles()->get();
        $totalSolicitado = $detallesOrden->sum(fn ($d) => \App\Modules\Stock\Services\ProductUnitService::quantity((int) $d->cantidad_solicitada, (int) $d->id_producto, $d->id_unidad));

        $compradoPorProd = [];
        $recibidoPorProd = [];
        $totalComprado = 0;
        $totalRecibido = 0;

        foreach ($comprasActivas as $c) {
            $dets = $c->relationLoaded('detalles') ? $c->detalles : $c->detalles()->get();
            foreach ($dets as $dc) {
                $idP = (int) $dc->id_producto;
                $cant = (int) $dc->cantidad > 0 ? \App\Modules\Stock\Services\ProductUnitService::quantity((int) $dc->cantidad, $idP, $dc->id_unidad) : 0;
                $rec = (int) $dc->cantidad_recibida > 0 ? \App\Modules\Stock\Services\ProductUnitService::quantity((int) $dc->cantidad_recibida, $idP, $dc->id_unidad) : 0;
                $compradoPorProd[$idP] = ($compradoPorProd[$idP] ?? 0) + $cant;
                $recibidoPorProd[$idP] = ($recibidoPorProd[$idP] ?? 0) + $rec;
                $totalComprado += $cant;
                $totalRecibido += $rec;
            }
        }

        $totalPendienteFacturar = 0;
        $totalPendienteRecibir = 0;
        if ($o->estado !== 'completada') {
            foreach ($detallesOrden as $do) {
                $idP = (int) $do->id_producto;
                $sol = \App\Modules\Stock\Services\ProductUnitService::quantity((int) $do->cantidad_solicitada, $idP, $do->id_unidad);
                $totalPendienteFacturar += max(0, $sol - ($compradoPorProd[$idP] ?? 0));
                $totalPendienteRecibir += max(0, $sol - ($recibidoPorProd[$idP] ?? 0));
            }
        }

        return [
            'id_orden' => $o->id_orden,
            'numero_orden' => $o->numero_orden,
            'id_proveedor' => $o->id_proveedor,
            'proveedor' => $o->proveedor?->razon_social,
            'total_estimado' => (float) $o->total_estimado,
            'estado' => $o->estado,
            'cierre_parcial' => (bool) ($o->cierre_parcial ?? false),
            'observaciones' => $o->observaciones ?? null,
            'fecha_creacion' => $o->fecha_creacion ? $o->fecha_creacion->format('Y-m-d') : null,
            'fecha_entrega_estimada' => $o->fecha_entrega_estimada ? $o->fecha_entrega_estimada->format('Y-m-d') : null,
            'fecha_modificacion' => $o->fecha_modificacion ? $o->fecha_modificacion->format('Y-m-d') : null,
            'fecha_envio' => $o->fecha_envio ? $o->fecha_envio->format('Y-m-d') : null,
            'fecha_cancelacion' => $o->fecha_cancelacion ? $o->fecha_cancelacion->format('Y-m-d') : null,
            'total_productos' => $totalSolicitado,
            'total_comprado' => $totalComprado,
            'total_recibido' => $totalRecibido,
            'total_pendiente' => $totalPendienteFacturar,
            'total_falta_recibir' => $totalPendienteRecibir,
            'id_compra' => $compraActiva ? (int) $compraActiva->id_compra : null,
            'numero_compra' => $compraActiva?->numero_compra,
            'estado_compra' => $compraActiva?->estado,
            'compras_vinculadas' => $comprasActivas->map(fn ($c) => [
                'id_compra' => (int) $c->id_compra,
                'numero_compra' => $c->numero_compra,
                'numero_comprobante' => $c->numero_comprobante,
                'importe_total' => (float) $c->importe_total,
                'estado' => $c->estado,
                'fecha_compra' => $c->fecha_compra ? $c->fecha_compra->format('Y-m-d') : null,
            ])->all(),
        ];
    }

    private function normalizarItems(array $items): array
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                if (! isset($item['cantidad_solicitada'])) {
                    if (isset($item['cantidad_pedida'])) {
                        $item['cantidad_solicitada'] = $item['cantidad_pedida'];
                    } elseif (isset($item['cantidad'])) {
                        $item['cantidad_solicitada'] = $item['cantidad'];
                    }
                }
            }

            return $item;
        }, $items);
    }

    /**
     * Valida los ítems de una orden. Devuelve null si es válido o un array de error.
     */
    private function validarItems(array $items): ?array
    {
        $validation = Validator::make(['items' => $items], [
            'items.*.id_producto' => 'required|integer|min:1',
            'items.*.id_unidad' => 'nullable|integer|min:1',
            'items.*.cantidad_solicitada' => 'required|integer|min:1|max:2147483647',
            'items.*.precio_estimado' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'regex:/^\\d+(?:\\.\\d{1,2})?$/D'],
        ]);
        if ($validation->fails()) {
            return ['status' => 'error', 'message' => $validation->errors()->first(), 'code' => 400];
        }
        foreach (collect($items)->sortBy('id_producto') as $item) {
            if (! isset($item['id_producto']) || (int) $item['id_producto'] <= 0) {
                return ['status' => 'error', 'message' => 'Cada ítem debe indicar un producto.', 'code' => 400];
            }
            $producto = Producto::query()->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->find((int) $item['id_producto']);
            if (! $producto || $producto->estado !== 'activo') {
                return [
                    'status' => 'error',
                    'message' => "El producto con ID {$item['id_producto']} no existe o no está activo.",
                    'code' => 400,
                ];
            }
            if (! isset($item['cantidad_solicitada']) || (int) $item['cantidad_solicitada'] <= 0) {
                return ['status' => 'error', 'message' => 'Cada ítem debe indicar una cantidad solicitada mayor a cero.', 'code' => 400];
            }
            if (! isset($item['precio_estimado']) || (float) $item['precio_estimado'] < 0) {
                return ['status' => 'error', 'message' => 'Cada ítem debe indicar un precio estimado válido.', 'code' => 400];
            }
        }

        return null;
    }

    /**
     * DETALLE_ORDEN.id_unidad es NOT NULL en el esquema fusionado. Cuando el item
     * no indica unidad se usa la unidad base del producto, que es la que asume
     * precio_estimado y cantidad_solicitada.
     */
    private function unidadDelItem(array $item, ?Producto $producto): int
    {
        return (int) ProductUnitService::resolve(
            (int) $item['id_producto'], isset($item['id_unidad']) ? (int) $item['id_unidad'] : null
        )->id_unidad;
    }

    /**
     * PC01 - Generar una orden de compra.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->has('items') && $request->has('detalles')) {
            $request->merge(['items' => $request->input('detalles')]);
        }
        if (is_array($request->input('items'))) {
            $request->merge(['items' => $this->normalizarItems($request->input('items'))]);
        }

        $validator = Validator::make($request->all(), [
            'id_proveedor' => 'required|integer',
            'fecha_creacion' => 'nullable|date|before_or_equal:today',
            'fecha_entrega_estimada' => 'nullable|date|after_or_equal:fecha_creacion',
            'items' => 'required|array|min:1|max:100',
        ], [
            'id_proveedor.required' => 'Debe seleccionar un proveedor.',
            'fecha_creacion.before_or_equal' => 'La fecha de creación no puede ser posterior a la fecha actual.',
            'fecha_entrega_estimada.after_or_equal' => 'La fecha de entrega estimada no puede ser anterior a la fecha de creación.',
            'items.required' => 'Debe agregar al menos un producto a la orden.',
            'items.min' => 'Debe agregar al menos un producto a la orden.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $proveedor = Proveedor::find((int) $request->input('id_proveedor'));
        if (! $proveedor) {
            return response()->json(['status' => 'error', 'message' => 'El proveedor seleccionado no existe.'], 404);
        }
        if ($proveedor->estado !== 'activo') {
            return response()->json(['status' => 'error', 'message' => 'No se puede crear una orden con un proveedor inactivo. (PV04)'], 400);
        }

        $items = $request->input('items');
        $error = $this->validarItems($items);
        if ($error) {
            return response()->json(['status' => $error['status'], 'message' => $error['message']], $error['code']);
        }

        $idUsuario = UsuarioActual::id($request);
        $fecha = $request->input('fecha_creacion') ?? Carbon::now()->toDateString();

        try {
            $orden = DB::transaction(function () use ($request, $proveedor, $items, $idUsuario, $fecha) {
                Producto::whereIn('id_producto', array_column($items, 'id_producto'))->orderBy('id_producto')->lockForUpdate()->get();
                $error = $this->validarItems($items);
                if ($error) {
                    abort(422, $error['message']);
                }
                $totalEstimado = 0;
                $detalles = [];

                foreach ($items as $item) {
                    $cantidad = (int) $item['cantidad_solicitada'];
                    $precio = (float) $item['precio_estimado'];
                    $subtotal = round($cantidad * $precio, 2);
                    $totalEstimado += $subtotal;
                    $producto = Producto::query()->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->find((int) $item['id_producto']);

                    $detalles[] = [
                        'id_producto' => (int) $item['id_producto'],
                        'id_unidad' => $this->unidadDelItem($item, $producto),
                        'cantidad_solicitada' => $cantidad,
                        'cantidad_sugerida' => isset($item['cantidad_sugerida']) ? (int) $item['cantidad_sugerida'] : $cantidad,
                        'origen' => $item['origen'] ?? 'manual',
                        'precio_estimado' => $precio,
                        'subtotal' => $subtotal,
                    ];
                }

                $totalEstimado = round($totalEstimado, 2);

                $orden = OrdenCompra::create([
                    'numero_orden' => $this->generarNumero(),
                    'id_proveedor' => $proveedor->id_proveedor,
                    'total_estimado' => $totalEstimado,
                    'estado' => 'pendiente',
                    'fecha_creacion' => $fecha,
                    'fecha_entrega_estimada' => $request->input('fecha_entrega_estimada') ?: null,
                    'id_usuario' => $idUsuario,
                ]);

                foreach ($detalles as $d) {
                    DetalleOrden::create(array_merge($d, ['id_orden' => $orden->id_orden]));
                }

                return $orden;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Orden de compra generada exitosamente.',
                'data' => $this->formato($orden),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al generar la orden: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera un número único de orden (PC01): OC-AAAA-nnnnnn.
     */
    private function generarNumero(): string
    {
        do {
            $numero = 'OC-'.Carbon::now()->format('Y').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (OrdenCompra::where('numero_orden', $numero)->exists());

        return $numero;
    }

    /**
     * PC02 - Listar y filtrar órdenes por proveedor y rango de fechas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OrdenCompra::with('proveedor');

        if ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', (int) $request->input('id_proveedor'));
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_creacion', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_creacion', '<=', $request->input('hasta'));
        }

        $estado = $request->input('estado');
        if ($estado && $estado !== 'todos' && in_array($estado, OrdenCompra::ESTADOS, true)) {
            $query->where('estado', $estado);
        }

        $ordenes = $query->orderByDesc('fecha_creacion')->orderByDesc('id_orden')->get();

        return response()->json([
            'status' => 'success',
            'data' => $ordenes->map(fn (OrdenCompra $o) => $this->formato($o)),
        ], 200);
    }

    /**
     * PC03 / PC06 - Detalle de una orden (productos, cantidades, estado).
     */
    public function show(int $id): JsonResponse
    {
        $order = OrdenCompra::with(['proveedor', 'compras.detalles', 'detalles.producto'])->findOrFail($id);
        $summary = $this->formato($order);
        $committed = []; $received = [];
        foreach ($order->compras->where('estado', '!=', 'cancelada') as $purchase) {
            foreach ($purchase->detalles as $d) {
                foreach (['cantidad' => 'committed', 'cantidad_recibida' => 'received'] as $field => $bucket) {
                    $quantity = (int) $d->$field;
                    if ($quantity > 0) ${$bucket}[$d->id_producto] = (${$bucket}[$d->id_producto] ?? 0) + ProductUnitService::quantity($quantity, (int) $d->id_producto, $d->id_unidad);
                }
            }
        }
        $details = $order->detalles->sortBy('id_detalle_orden')->map(function ($d) use (&$committed, &$received) {
            $product = (int) $d->id_producto;
            $unit = ProductUnitService::resolve($product, $d->id_unidad);
            $factor = (int) $unit->equivalencia_base;
            $base = ProductUnitService::quantity((int) $d->cantidad_solicitada, $product, $d->id_unidad);
            $bought = min($base, $committed[$product] ?? 0); $arrived = min($base, $received[$product] ?? 0);
            $committed[$product] = max(0, ($committed[$product] ?? 0) - $bought);
            $received[$product] = max(0, ($received[$product] ?? 0) - $arrived);
            $baseUnit = ProductUnitService::resolve($product);
            return ['id_detalle_orden' => $d->id_detalle_orden, 'id_producto' => $product, 'id_unidad' => $d->id_unidad,
                'codigo' => $d->producto?->codigo, 'nombre' => $d->producto?->nombre, 'descripcion' => $d->producto?->descripcion,
                'cantidad_solicitada' => (int) $d->cantidad_solicitada, 'cantidad_comprada' => $bought / $factor,
                'cantidad_recibida' => $arrived / $factor, 'cantidad_pendiente' => max(0, $base - $bought) / $factor,
                'falta_recibir' => max(0, $base - $arrived) / $factor, 'cantidad_pendiente_base' => max(0, $base - $bought),
                'id_unidad_compra' => $baseUnit->id_unidad, 'precio_estimado_base' => round($d->precio_estimado / $factor, 2),
                'precio_estimado' => (float) $d->precio_estimado, 'subtotal' => (float) $d->subtotal, 'origen' => $d->origen];
        })->values();
        return response()->json(['status' => 'success', 'data' => [...$summary, 'orden' => $summary,
            'total_solicitado' => $summary['total_productos'], 'detalles' => $details,
            'compras' => $order->compras->map(fn ($c) => ['id_compra' => $c->id_compra, 'numero_compra' => $c->numero_compra,
                'estado' => $c->estado, 'importe_total' => $c->importe_total, 'total_pagado' => $c->totalPagado(),
                'saldo_a_pagar' => $c->estado === 'cancelada' ? 0 : max(0, $c->saldoFinal())])]]);
    }

    /**
     * PC04 - Editar una orden pendiente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $orden = OrdenCompra::with('proveedor')->lockForUpdate()->find($id);
            if (! $orden) {
                return response()->json(['status' => 'error', 'message' => 'La orden de compra no existe.'], 404);
            }

            if ($orden->estado !== 'pendiente') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo se pueden editar órdenes pendientes. Una vez enviada no puede modificarse. (PC04)',
                ], 400);
            }

            if ($orden->proveedor && $orden->proveedor->estado !== 'activo') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede editar una orden de compra de un proveedor inactivo. (PV04)',
                ], 400);
            }

            if (! $request->has('items') && $request->has('detalles')) {
                $request->merge(['items' => $request->input('detalles')]);
            }
            if (is_array($request->input('items'))) {
                $request->merge(['items' => $this->normalizarItems($request->input('items'))]);
            }

            $validator = Validator::make($request->all(), [
                'fecha_creacion' => 'nullable|date|before_or_equal:today',
                'fecha_entrega_estimada' => 'nullable|date|after_or_equal:fecha_creacion',
                'items' => 'required|array|min:1|max:100',
            ], [
                'fecha_creacion.before_or_equal' => 'La fecha no puede ser posterior a la fecha actual.',
                'fecha_entrega_estimada.after_or_equal' => 'La fecha de entrega estimada no puede ser anterior a la fecha de creación.',
                'items.required' => 'Debe agregar al menos un producto a la orden.',
                'items.min' => 'Debe agregar al menos un producto a la orden.',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
            }

            $items = $request->input('items');
            $error = $this->validarItems($items);
            if ($error) {
                return response()->json(['status' => $error['status'], 'message' => $error['message']], $error['code']);
            }

            try {
                $orden = DB::transaction(function () use ($orden, $items, $request) {
                    $totalEstimado = 0;
                    $nuevos = [];

                    foreach ($items as $item) {
                        $cantidad = (int) $item['cantidad_solicitada'];
                        $precio = (float) $item['precio_estimado'];
                        $subtotal = round($cantidad * $precio, 2);
                        $totalEstimado += $subtotal;
                        $producto = Producto::query()->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->find((int) $item['id_producto']);

                        $nuevos[] = [
                            'id_producto' => (int) $item['id_producto'],
                            'id_unidad' => $this->unidadDelItem($item, $producto),
                            'cantidad_solicitada' => $cantidad,
                            'cantidad_sugerida' => isset($item['cantidad_sugerida']) ? (int) $item['cantidad_sugerida'] : $cantidad,
                            'origen' => $item['origen'] ?? 'manual',
                            'precio_estimado' => $precio,
                            'subtotal' => $subtotal,
                        ];
                    }

                    $totalEstimado = round($totalEstimado, 2);

                    $orden->detalles()->delete();
                    foreach ($nuevos as $n) {
                        DetalleOrden::create(array_merge($n, ['id_orden' => $orden->id_orden]));
                    }

                    $orden->total_estimado = $totalEstimado;
                    $orden->fecha_creacion = $request->input('fecha_creacion') ?: $orden->fecha_creacion;
                    $orden->fecha_entrega_estimada = $request->input('fecha_entrega_estimada') ?: null;
                    $orden->fecha_modificacion = Carbon::now()->toDateString();
                    $orden->save();

                    return $orden;
                });

                return response()->json([
                    'status' => 'success',
                    'message' => 'Orden de compra editada exitosamente.',
                    'data' => $this->formato($orden),
                ], 200);
            } catch (ValidationException $e) {
                throw $e;
            } catch (HttpExceptionInterface $e) {
                throw $e;
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al editar la orden: '.$e->getMessage(),
                ], 500);
            }
        }, 3);
    }

    /**
     * PC05 - Cancelar una orden pendiente.
     */
    public function cancelar(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $orden = OrdenCompra::lockForUpdate()->find($id);
            if (! $orden) {
                return response()->json(['status' => 'error', 'message' => 'La orden de compra no existe.'], 404);
            }

            if (! in_array($orden->estado, ['pendiente', 'enviada'], true) || $orden->compras()->where('estado', '!=', 'cancelada')->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Una orden completada, cancelada o con compras activas no puede cancelarse; sólo pendientes o enviadas sin compras. (PC05)',
                ], 400);
            }

            LogAuditoria::create(['tabla_afectada' => 'orden_compra', 'id_registro' => $id, 'accion' => 'UPDATE', 'valores_anteriores' => ['estado' => $orden->estado], 'valores_nuevos' => ['estado' => 'cancelada'], 'id_usuario' => UsuarioActual::id(), 'fecha_hora' => now()]);
            $orden->estado = 'cancelada';
            $orden->fecha_cancelacion = Carbon::now()->toDateString();
            $orden->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Orden de compra cancelada. Se conserva el historial.',
                'data' => $this->formato($orden),
            ], 200);
        }, 3);
    }

    /**
     * Acción complementaria: enviar una orden pendiente (necesaria para PC04/PC05/PC06).
     * Marca la fecha de envío y pasa la orden a estado 'enviada'.
     */
    public function enviar(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $orden = OrdenCompra::with('proveedor')->lockForUpdate()->find($id);
            if (! $orden) {
                return response()->json(['status' => 'error', 'message' => 'La orden de compra no existe.'], 404);
            }

            if ($orden->estado !== 'pendiente') {
                return response()->json(['status' => 'error', 'message' => 'Solo se pueden enviar órdenes pendientes.'], 400);
            }

            if ($orden->proveedor && $orden->proveedor->estado !== 'activo') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede enviar una orden a un proveedor inactivo. (PV04)',
                ], 400);
            }

            $fechaEnv = $request->input('fecha_envio') ?? Carbon::now()->toDateString();
            LogAuditoria::create(['tabla_afectada' => 'orden_compra', 'id_registro' => $id, 'accion' => 'UPDATE', 'valores_anteriores' => ['estado' => $orden->estado], 'valores_nuevos' => ['estado' => 'enviada'], 'id_usuario' => UsuarioActual::id($request), 'fecha_hora' => now()]);
            $orden->estado = 'enviada';
            $orden->fecha_envio = $fechaEnv;
            $orden->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Orden de compra enviada al proveedor.',
                'data' => $this->formato($orden),
            ], 200);
        }, 3);
    }

    /**
     * PC06 - Visualizar y registrar avance de estado de una orden enviada.
     * Permite avanzar a 'recibida_parcialmente' o 'completada'.
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $orden = OrdenCompra::lockForUpdate()->find($id);
            if (! $orden) {
                return response()->json(['status' => 'error', 'message' => 'La orden de compra no existe.'], 404);
            }

            $validator = Validator::make($request->all(), [
                'estado' => 'required|string|in:recibida_parcialmente,completada',
            ], [
                'estado.required' => 'Debe indicar el nuevo estado.',
                'estado.in' => 'El nuevo estado debe ser recibida_parcialmente o completada.',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
            }

            if (! in_array($orden->estado, ['enviada', 'recibida_parcialmente'], true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo se pueden marcar como recibidas las órdenes que han sido enviadas previamente (PC06).',
                ], 400);
            }

            $nuevoEstado = $request->input('estado');
            $verified = app(PurchaseOrderProgress::class)->sync($orden);
            if ($nuevoEstado === 'completada' && $verified === 'recibida_parcialmente' && $request->boolean('confirmar_cierre_parcial')) {
                $purchases = $orden->compras()->where('estado', '!=', 'cancelada')->orderBy('id_compra')->lockForUpdate()->get();
                abort_if($purchases->contains(fn ($p) => $p->estado === 'pendiente'), 422,
                    'Hay compras sin recepción; regularice o anule esos comprobantes antes del cierre de la orden.');
                foreach ($purchases->where('estado', 'parcialmente_recibida') as $purchase) {
                    app(\App\Modules\Compras\Services\PurchaseLifecycle::class)->closePartial($purchase->id_compra, UsuarioActual::id($request));
                }
                $orden->refresh();
                $orden->update(['cierre_parcial' => true]);
                $verified = app(PurchaseOrderProgress::class)->sync($orden);
            }
            abort_unless($nuevoEstado === $verified, 422, 'El estado solicitado no coincide con las recepciones físicas registradas.');
            $orden->estado = $nuevoEstado;
            $orden->fecha_modificacion = Carbon::now()->toDateString();
            $orden->save();

            return response()->json([
                'status' => 'success',
                'message' => "Estado de la orden de compra actualizado exitosamente a {$nuevoEstado}.",
                'data' => $this->formato($orden),
            ], 200);
        }, 3);
    }
}
