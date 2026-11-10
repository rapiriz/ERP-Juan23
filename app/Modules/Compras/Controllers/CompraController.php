<?php

declare(strict_types=1);

namespace App\Modules\Compras\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compras\Models\Compra;
use App\Modules\Compras\Models\DetalleCompra;
use App\Modules\Compras\Models\Recepcion;
use App\Modules\PedidosDeCompra\Models\OrdenCompra;
use App\Modules\PedidosDeCompra\Services\PurchaseOrderProgress;
use App\Modules\Productos\Models\Producto;
use App\Modules\Proveedores\Models\ProductoProveedor;
use App\Modules\Proveedores\Models\Proveedor;
use App\Modules\Proveedores\Services\HistorialPrecioService;
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
 * Módulo Compras.
 *
 * C01 - Registrar compras de mercadería.
 * C02 - Consultar compras realizadas.
 * C03 - Consultar el detalle de una compra.
 * C04 - Modificar una compra pendiente.
 * C05 - Cancelar una compra pendiente.
 */
class CompraController extends Controller
{
    /**
     * Formato de una compra (sin detalles).
     */
    private function formato(Compra $c): array
    {
        $importeTotal = round((float) $c->importe_total, 2);
        $totalPagado = $c->relationLoaded('pagos')
            ? round((float) $c->pagos->sum('importe'), 2)
            : $c->totalPagado();
        $saldoAPagar = $c->estado === 'cancelada'
            ? 0.0
            : max(0.0, round($importeTotal - $totalPagado, 2));

        if ($c->estado === 'cancelada') {
            $estadoPago = 'cancelada';
        } elseif ($saldoAPagar <= 0 && $importeTotal > 0) {
            $estadoPago = 'pagada';
        } elseif ($totalPagado > 0) {
            $estadoPago = 'pago_parcial';
        } else {
            $estadoPago = 'pendiente_pago';
        }

        return [
            'id_compra' => $c->id_compra,
            'numero_compra' => $c->numero_compra,
            'numero_comprobante' => $c->numero_comprobante,
            'id_orden' => $c->id_orden ? (int) $c->id_orden : null,
            'numero_orden' => $c->orden?->numero_orden,
            'id_proveedor' => $c->id_proveedor,
            'proveedor' => $c->proveedor?->razon_social,
            'proveedor_cuit' => $c->proveedor?->cuit,
            'proveedor_direccion' => $c->proveedor?->direccion,
            'proveedor_telefono' => $c->proveedor?->telefono,
            'proveedor_email' => $c->proveedor?->email,
            'importe_total' => $importeTotal,
            'saldo_pendiente' => (float) $c->saldo_pendiente,
            'total_pagado' => $totalPagado,
            'saldo_a_pagar' => $saldoAPagar,
            'estado_pago' => $estadoPago,
            'estado' => $c->estado,
            'cierre_parcial' => (bool) ($c->cierre_parcial ?? false),
            'observaciones' => $c->observaciones ?? null,
            'fecha_entrega_prevista' => $c->fecha_entrega_prevista?->format('Y-m-d'),
            'fecha_compra' => $c->fecha_compra ? $c->fecha_compra->format('Y-m-d') : null,
            'fecha_vencimiento' => $c->fecha_vencimiento ? $c->fecha_vencimiento->format('Y-m-d') : null,
            'fecha_cancelacion' => $c->fecha_cancelacion ? $c->fecha_cancelacion->format('Y-m-d') : null,
            'total_productos' => $c->detalles()->sum('cantidad'),
        ];
    }

    /**
     * Valida los ítems de una compra. Devuelve null si es válido o un array
     * con la respuesta de error.
     */
    private function validarItems(array $items): ?array
    {
        $validation = Validator::make(['items' => $items], [
            'items.*.id_producto' => 'required|integer|min:1',
            'items.*.id_unidad' => 'nullable|integer|min:1',
            'items.*.cantidad' => 'required|integer|min:1|max:2147483647',
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'regex:/^\\d+(?:\\.\\d{1,2})?$/D'],
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
            if (! isset($item['cantidad']) || (int) $item['cantidad'] <= 0) {
                return ['status' => 'error', 'message' => 'Cada ítem debe indicar una cantidad mayor a cero.', 'code' => 400];
            }
            if (! isset($item['precio_unitario']) || (float) $item['precio_unitario'] < 0) {
                return ['status' => 'error', 'message' => 'Cada ítem debe indicar un precio unitario válido.', 'code' => 400];
            }
        }

        return null;
    }

    /**
     * C01 - Registrar una compra de mercadería.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->has('items') && $request->has('detalles')) {
            $request->merge(['items' => $request->input('detalles')]);
        }
        if (! $request->has('fecha_compra') && $request->has('fecha')) {
            $request->merge(['fecha_compra' => $request->input('fecha')]);
        }
        if (! $request->has('numero_comprobante') && $request->has('numero_factura')) {
            $request->merge(['numero_comprobante' => $request->input('numero_factura')]);
        }

        $validator = Validator::make($request->all(), [
            'id_proveedor' => 'required|integer',
            'id_orden' => 'nullable|integer|exists:orden_compra,id_orden',
            'fecha_compra' => 'nullable|date|before_or_equal:today',
            'fecha_vencimiento' => 'nullable|date',
            'fecha_entrega_prevista' => 'nullable|date_format:Y-m-d',
            'numero_comprobante' => 'nullable|string|max:50',
            'items' => 'required|array|min:1|max:100',
        ], [
            'id_proveedor.required' => 'Debe seleccionar un proveedor.',
            'id_orden.exists' => 'La orden de compra vinculada no existe.',
            'fecha_compra.before_or_equal' => 'La fecha de la compra no puede ser posterior a la fecha actual.',
            'numero_comprobante.max' => 'El número de comprobante no puede superar los 50 caracteres.',
            'items.required' => 'Debe agregar al menos un producto a la compra.',
            'items.min' => 'Debe agregar al menos un producto a la compra.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $proveedor = Proveedor::find((int) $request->input('id_proveedor'));
        if (! $proveedor) {
            return response()->json(['status' => 'error', 'message' => 'El proveedor seleccionado no existe.'], 404);
        }
        if ($proveedor->estado !== 'activo') {
            return response()->json(['status' => 'error', 'message' => 'No se puede crear una compra con un proveedor inactivo.'], 400);
        }

        $items = $request->input('items');

        $error = $this->validarItems($items);
        if ($error) {
            return response()->json(['status' => $error['status'], 'message' => $error['message']], $error['code']);
        }

        $idUsuario = UsuarioActual::id($request);
        $fecha = $request->input('fecha_compra') ?? Carbon::now()->toDateString();
        $idOrden = $request->filled('id_orden') ? (int) $request->input('id_orden') : null;

        try {
            $compra = DB::transaction(function () use ($request, $proveedor, $items, $idUsuario, $fecha, $idOrden) {
                if ($idOrden) {
                    $order = OrdenCompra::whereKey($idOrden)->lockForUpdate()->firstOrFail();
                    abort_unless((int) $order->id_proveedor === (int) $proveedor->id_proveedor, 409, 'La orden pertenece a otro proveedor.');
                    abort_unless(in_array($order->estado, ['pendiente', 'enviada', 'recibida_parcialmente'], true), 409, 'La orden está '.$order->estado.' y no admite nuevas compras.');
                }
                $productIds = array_column($items, 'id_producto');
                if ($idOrden) {
                    $productIds = array_merge($productIds, $order->detalles()->pluck('id_producto')->all());
                }
                Producto::whereIn('id_producto', $productIds)->orderBy('id_producto')->lockForUpdate()->get();
                if ($error = $this->validarItems($items)) {
                    abort(422, $error['message']);
                }
                if ($idOrden) {
                    app(PurchaseOrderProgress::class)->requirePurchase($order, $items);
                }
                if ($idOrden && $order->estado === 'pendiente') {
                    $order->update(['estado' => 'enviada', 'fecha_envio' => today(), 'fecha_modificacion' => today()]);
                    DB::table('log_auditoria')->insert(['tabla_afectada' => 'orden_compra', 'id_registro' => $idOrden, 'accion' => 'UPDATE',
                        'valores_anteriores' => json_encode(['estado' => 'pendiente']), 'valores_nuevos' => json_encode(['estado' => 'enviada', 'origen' => 'registro_compra']),
                        'id_usuario' => $idUsuario, 'fecha_hora' => now()]);
                }
                $importeTotal = 0;
                $detalles = [];

                foreach ($items as $item) {
                    $cantidad = (int) $item['cantidad'];
                    $precio = (float) $item['precio_unitario'];
                    $subtotal = round($cantidad * $precio, 2);
                    $importeTotal += $subtotal;
                    $detalles[] = [
                        'id_producto' => (int) $item['id_producto'],
                        'id_unidad' => $this->unidadDelItem($item),
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio,
                        'subtotal' => $subtotal,
                    ];
                }

                $importeTotal = round($importeTotal, 2);
                $fechaVenc = $request->input('fecha_vencimiento') ?: Carbon::parse($fecha)->addDays(30)->toDateString();

                $compra = Compra::create([
                    'numero_compra' => $this->generarComprobante(),
                    'numero_comprobante' => $request->input('numero_comprobante') ?: null,
                    'id_proveedor' => $proveedor->id_proveedor,
                    'id_orden' => $idOrden,
                    'importe_total' => $importeTotal,
                    'saldo_pendiente' => $importeTotal,
                    'estado' => 'pendiente',
                    'fecha_compra' => $fecha,
                    'fecha_entrega_prevista' => $request->input('fecha_entrega_prevista') ?: ($proveedor->plazo_entrega_dias === null ? null : Carbon::parse($fecha)->addDays(max(0, (int) $proveedor->plazo_entrega_dias))->toDateString()),
                    'fecha_vencimiento' => $fechaVenc,
                    'id_usuario' => $idUsuario,
                ]);

                foreach ($detalles as $d) {
                    DetalleCompra::create([
                        'id_compra' => $compra->id_compra,
                        'id_producto' => $d['id_producto'],
                        'id_unidad' => $d['id_unidad'],
                        'cantidad' => $d['cantidad'],
                        'cantidad_recibida' => 0,
                        'precio_unitario' => $d['precio_unitario'],
                        'subtotal' => $d['subtotal'],
                    ]);

                    // PV05: Asociar automáticamente el producto al proveedor al registrar una compra
                    // y actualizar el precio_acordado con el costo facturado.
                    $factor = (float) ProductUnitService::resolve((int) $d['id_producto'], (int) $d['id_unidad'])->equivalencia_base;
                    $precioCompra = round((float) $d['precio_unitario'] / $factor, 2);
                    $asocProv = ProductoProveedor::where('id_producto', $d['id_producto'])
                        ->where('id_proveedor', $proveedor->id_proveedor)
                        ->first();
                    if (! $asocProv) {
                        $tieneOtroPrincipal = ProductoProveedor::where('id_producto', $d['id_producto'])
                            ->where('activo', true)
                            ->where('es_proveedor_principal', true)
                            ->exists();

                        $asocProv = ProductoProveedor::create([
                            'id_producto' => $d['id_producto'],
                            'id_proveedor' => $proveedor->id_proveedor,
                            'es_proveedor_principal' => ! $tieneOtroPrincipal,
                            'precio_acordado' => $precioCompra,
                            'activo' => true,
                            'fecha_asociacion' => $fecha,
                            'id_usuario' => $idUsuario,
                        ]);
                    } else {
                        $asocProv->update(['precio_acordado' => $precioCompra, 'activo' => true, 'fecha_desasociacion' => null, 'id_usuario' => $idUsuario]);
                    }

                    // PV06: Registrar snapshot de precio en el historial del proveedor vinculado a la compra
                    if ($asocProv) {
                        app(HistorialPrecioService::class)->registrarSnapshot(
                            $asocProv->id_producto_proveedor,
                            (float) $d['precio_unitario'],
                            $idUsuario,
                            $compra->id_compra,
                            $d['id_unidad'],
                            $fecha
                        );
                        if ($asocProv->es_proveedor_principal && $precioCompra > 0) {
                            Producto::findOrFail($d['id_producto'])->sincronizarCostoReposicion($idUsuario);
                        }
                    }
                }

                if ($request->boolean('recibir_ahora')) {
                    $receiptItems = [];
                    foreach ($compra->detalles()->orderBy('id_detalle_compra')->get()->values() as $i => $detail) {
                        $quantity = (int) ($items[$i]['cantidad_recibida'] ?? $detail->cantidad);
                        abort_if($quantity > (int) $detail->cantidad, 422, 'La recepción inicial excede la cantidad comprada.');
                        if ($quantity > 0) $receiptItems[] = ['id_detalle_compra' => $detail->id_detalle_compra,
                            'id_producto' => $detail->id_producto, 'id_unidad' => $detail->id_unidad, 'cantidad_recibida' => $quantity,
                            'nro_lote' => $items[$i]['nro_lote'] ?? null, 'fecha_vencimiento' => $items[$i]['fecha_vencimiento'] ?? null];
                    }
                    if ($receiptItems) {
                        $receipt = Request::create('/api/compras/'.$compra->id_compra.'/recepciones', 'POST', ['items' => $receiptItems]);
                        $receipt->setUserResolver(fn () => $request->user());
                        $response = app(RecepcionController::class)->store($receipt, $compra->id_compra);
                        abort_if($response->getStatusCode() >= 400, $response->getStatusCode(), $response->getData(true)['message'] ?? 'Recepción inválida.');
                        $compra->refresh();
                    }
                }
                return $compra;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Compra registrada exitosamente.',
                'data' => $this->formato($compra),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar la compra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * DETALLE_COMPRA.id_unidad es NOT NULL en el esquema fusionado. Cuando el item
     * no indica unidad se usa la unidad base del producto, que es la que asume
     * cantidad y precio_unitario.
     */
    private function unidadDelItem(array $item): int
    {
        return (int) ProductUnitService::resolve(
            (int) $item['id_producto'], isset($item['id_unidad']) ? (int) $item['id_unidad'] : null
        )->id_unidad;
    }

    /**
     * Genera un identificador único de comprobante (C01): NCC-AAAA-000000.
     */
    private function generarComprobante(): string
    {
        do {
            $numero = 'NCC-'.Carbon::now()->format('Y').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Compra::where('numero_compra', $numero)->exists());

        return $numero;
    }

    /**
     * C02 - Listar y filtrar compras por proveedor y rango de fechas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Compra::with(['proveedor', 'orden', 'pagos']);

        if ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', (int) $request->input('id_proveedor'));
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha_compra', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_compra', '<=', $request->input('hasta'));
        }

        $estado = $request->input('estado');
        if ($estado && in_array($estado, Compra::ESTADOS, true)) {
            $query->where('estado', $estado);
        }

        $compras = $query->orderByDesc('fecha_compra')->orderByDesc('id_compra')->get();

        return response()->json([
            'status' => 'success',
            'data' => $compras->map(fn (Compra $c) => $this->formato($c)),
        ], 200);
    }

    /**
     * C09 - Visualizar deudas con proveedores (Issue #42).
     *
     * Consulta las compras no canceladas que tengan saldo pendiente de pago
     * (importe_total - total_pagado > 0), con detalle agrupado por proveedor,
     * compras pendientes y fechas de vencimiento próximas.
     */
    public function deudas(Request $request): JsonResponse
    {
        $query = Compra::with(['proveedor', 'pagos'])
            ->where('estado', '!=', 'cancelada');

        if ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', (int) $request->input('id_proveedor'));
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_compra', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_compra', '<=', $request->input('hasta'));
        }

        $compras = $query->orderBy('fecha_vencimiento', 'asc')
            ->orderBy('fecha_compra', 'desc')
            ->get();

        $deudasPorProveedor = [];
        $totalDeudaGeneral = 0.0;
        $todasComprasConDeuda = [];

        foreach ($compras as $c) {
            $totalPagado = round((float) $c->pagos->sum('importe'), 2);
            $importeTotal = round((float) $c->importe_total, 2);
            $saldoAPagar = max(0.0, round($importeTotal - $totalPagado, 2));

            if ($saldoAPagar <= 0) {
                continue;
            }

            $totalDeudaGeneral += $saldoAPagar;
            $idProv = $c->id_proveedor;

            $diasParaVencer = null;
            $estaVencida = false;
            if ($c->fecha_vencimiento) {
                $hoy = Carbon::today();
                $venc = Carbon::parse($c->fecha_vencimiento);
                $diasParaVencer = (int) $hoy->diffInDays($venc, false);
                $estaVencida = $diasParaVencer < 0;
            }

            $itemCompra = [
                'id_compra' => $c->id_compra,
                'numero_compra' => $c->numero_compra,
                'numero_comprobante' => $c->numero_comprobante,
                'fecha_compra' => $c->fecha_compra ? $c->fecha_compra->format('Y-m-d') : null,
            'fecha_entrega_prevista' => $c->fecha_entrega_prevista?->format('Y-m-d'),
                'fecha_vencimiento' => $c->fecha_vencimiento ? $c->fecha_vencimiento->format('Y-m-d') : null,
                'importe_total' => $importeTotal,
                'total_pagado' => $totalPagado,
                'saldo_a_pagar' => $saldoAPagar,
                'estado' => $c->estado,
                'dias_para_vencer' => $diasParaVencer,
                'esta_vencida' => $estaVencida,
                'proveedor' => $c->proveedor?->razon_social,
                'id_proveedor' => $idProv,
            ];

            $todasComprasConDeuda[] = $itemCompra;

            if (! isset($deudasPorProveedor[$idProv])) {
                $deudasPorProveedor[$idProv] = [
                    'id_proveedor' => $idProv,
                    'razon_social' => $c->proveedor?->razon_social ?? "Proveedor #{$idProv}",
                    'cuit' => $c->proveedor?->cuit,
                    'telefono' => $c->proveedor?->telefono,
                    'email' => $c->proveedor?->email,
                    'saldo_adeudado' => 0.0,
                    'total_adeudado' => 0.0,
                    'cantidad_compras' => 0,
                    'proximo_vencimiento' => null,
                    'compras' => [],
                ];
            }

            $nuevoSaldo = round($deudasPorProveedor[$idProv]['saldo_adeudado'] + $saldoAPagar, 2);
            $deudasPorProveedor[$idProv]['saldo_adeudado'] = $nuevoSaldo;
            $deudasPorProveedor[$idProv]['total_adeudado'] = $nuevoSaldo;
            $deudasPorProveedor[$idProv]['cantidad_compras']++;
            $deudasPorProveedor[$idProv]['compras'][] = $itemCompra;

            if ($c->fecha_vencimiento) {
                $fechaV = $c->fecha_vencimiento->format('Y-m-d');
                if ($deudasPorProveedor[$idProv]['proximo_vencimiento'] === null || $fechaV < $deudasPorProveedor[$idProv]['proximo_vencimiento']) {
                    $deudasPorProveedor[$idProv]['proximo_vencimiento'] = $fechaV;
                }
            }
        }

        $listaProveedores = collect(array_values($deudasPorProveedor))
            ->sortByDesc('saldo_adeudado')
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'saldo_adeudado_total' => round($totalDeudaGeneral, 2),
                'proveedores_con_deuda' => count($listaProveedores),
                'compras_con_deuda' => count($todasComprasConDeuda),
                'por_proveedor' => $listaProveedores,
                'proveedores' => $listaProveedores,
                'compras_pendientes' => $todasComprasConDeuda,
            ],
        ], 200);
    }

    /**
     * C03 - Consultar el detalle de una compra.
     */
    public function show(int $id): JsonResponse
    {
        $compra = Compra::with(['proveedor', 'orden', 'pagos', 'detalles.producto', 'detalles.unidad', 'recepciones.detalles.producto'])->find($id);
        if (! $compra) {
            return response()->json(['status' => 'error', 'message' => 'La compra no existe.'], 404);
        }

        $totalRecibido = $compra->detalles()->sum('cantidad_recibida');
        $totalComprado = $compra->detalles()->sum('cantidad');

        $detalles = $compra->detalles->map(function (DetalleCompra $d) {
            return [
                'id_detalle' => $d->id_detalle_compra,
                'id_detalle_compra' => $d->id_detalle_compra,
                'id_unidad' => $d->id_unidad,
                'presentacion' => $d->unidad?->nombre_unidad,
                'equivalencia_base' => $d->unidad ? (float) $d->unidad->equivalencia_base : 1,
                'id_producto' => $d->id_producto,
                'codigo' => $d->producto?->codigo,
                'nombre' => $d->producto?->nombre,
                'descripcion' => $d->producto?->descripcion,
                'cantidad' => (int) $d->cantidad,
                'cantidad_recibida' => (int) $d->cantidad_recibida,
                'cantidad_pendiente' => max(0, (int) $d->cantidad - (int) $d->cantidad_recibida),
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal' => (float) $d->subtotal,
            ];
        });

        $recepciones = $compra->recepciones->map(function (Recepcion $r) {
            return [
                'id_recepcion' => $r->id_recepcion,
                'fecha_recepcion' => $r->fecha_recepcion ? $r->fecha_recepcion->format('Y-m-d') : null,
                'total_recibido' => (int) $r->detalles->sum('cantidad_recibida'),
                'productos' => $r->detalles->map(fn ($dr) => [
                    'id_producto' => $dr->id_producto,
                    'codigo' => $dr->producto?->codigo,
                    'nombre' => $dr->producto?->nombre,
                    'descripcion' => $dr->producto?->descripcion,
                    'cantidad_recibida' => (int) $dr->cantidad_recibida,
                ]),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'compra' => $this->formato($compra),
                'total_comprado' => (int) $totalComprado,
                'total_recibido' => (int) $totalRecibido,
                'total_pendiente' => max(0, (int) $totalComprado - (int) $totalRecibido),
                'pagos' => $this->datosPagos($compra),
                'detalles' => $detalles,
                'recepciones' => $recepciones,
            ],
        ], 200);
    }

    /**
     * C07 - Visualizar el estado de una compra.
     *
     * Endpoint dedicado: devuelve el estado y el avance en unidades y en dinero,
     * sin el detalle completo de items y recepciones que trae show().
     *
     * Ojo con los dos saldos: saldo_pendiente es el importe de mercadería todavía
     * no recibida (lo calcula RecepcionController, C06), mientras saldo_a_pagar es
     * la deuda real = importe_total - total_pagado (C08).
     */
    public function estado(int $id): JsonResponse
    {
        $compra = Compra::with(['proveedor'])->find($id);
        if (! $compra) {
            return response()->json(['status' => 'error', 'message' => 'La compra no existe.'], 404);
        }

        $comprado = (int) $compra->detalles()->sum('cantidad');
        $recibido = (int) $compra->detalles()->sum('cantidad_recibida');
        $pendiente = max(0, $comprado - $recibido);
        $pagos = $this->datosPagos($compra);

        $porProducto = $compra->detalles()
            ->with('producto:id_producto,codigo,descripcion')
            ->get()
            ->map(fn (DetalleCompra $d) => [
                'id_producto' => $d->id_producto,
                'codigo' => $d->producto?->codigo,
                'descripcion' => $d->producto?->descripcion,
                'comprado' => (int) $d->cantidad,
                'recibido' => (int) $d->cantidad_recibida,
                'pendiente' => max(0, (int) $d->cantidad - (int) $d->cantidad_recibida),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id_compra' => $compra->id_compra,
                'numero_compra' => $compra->numero_compra,
                'numero_comprobante' => $compra->numero_comprobante,
                'proveedor' => $compra->proveedor?->razon_social,
                'estado' => $compra->estado,
                'unidades' => [
                    'compradas' => $comprado,
                    'recibidas' => $recibido,
                    'pendientes' => $pendiente,
                    'porcentaje_recibido' => $comprado > 0 ? round($recibido * 100 / $comprado, 2) : 0.0,
                ],
                'importes' => [
                    'importe_total' => round((float) $compra->importe_total, 2),
                    // Mercadería aún no recibida (C06)
                    'saldo_pendiente' => round((float) $compra->saldo_pendiente, 2),
                    // Deuda real por pagos (C08)
                    'total_pagado' => $pagos['total_pagado'],
                    'saldo_a_pagar' => $pagos['saldo_a_pagar'],
                ],
                'pagos' => $pagos,
                'por_producto' => $porProducto,
            ],
        ], 200);
    }

    /** Datos de pago de la compra (C08), reutilizados por show() y estado(). */
    private function datosPagos(Compra $compra): array
    {
        $totalPagado = $compra->totalPagado();
        $importeTotal = round((float) $compra->importe_total, 2);

        return [
            'cantidad' => $compra->pagos()->count(),
            'total_pagado' => $totalPagado,
            'saldo_a_pagar' => $compra->estado === 'cancelada' ? 0.0 : max(0.0, round($importeTotal - $totalPagado, 2)),
            'completos' => $importeTotal - $totalPagado <= 0,
            'por_metodo' => $compra->pagos()
                ->selectRaw('metodo_pago, count(*) as cantidad_pagos, sum(importe) as total')
                ->groupBy('metodo_pago')
                ->orderBy('metodo_pago')
                ->get()
                ->map(fn ($r) => [
                    'metodo_pago' => $r->metodo_pago,
                    'cantidad_pagos' => (int) $r->cantidad_pagos,
                    'total' => round((float) $r->total, 2),
                ]),
            'lista' => \App\Modules\Compras\Models\PagoProveedor::where('id_compra', $compra->id_compra)
                ->orderBy('fecha_pago')->orderBy('id_pago')->get()
                ->map(fn ($p) => \App\Modules\Compras\Services\PagoCompraService::formatear($p))->all(),
        ];
    }

    /**
     * C04 - Modificar una compra pendiente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $compra = Compra::with('proveedor')->find($id);
        if (! $compra) {
            return response()->json(['status' => 'error', 'message' => 'La compra no existe.'], 404);
        }

        if ($compra->estado !== 'pendiente') {
            return response()->json([
                'status' => 'error',
                'message' => 'Solo se pueden modificar compras pendientes. (C04)',
            ], 400);
        }

        if ($compra->proveedor && $compra->proveedor->estado !== 'activo') {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede modificar una compra de un proveedor inactivo. (PV04)',
            ], 400);
        }

        if (! $request->has('items') && $request->has('detalles')) {
            $request->merge(['items' => $request->input('detalles')]);
        }
        if (! $request->has('fecha_compra') && $request->has('fecha')) {
            $request->merge(['fecha_compra' => $request->input('fecha')]);
        }
        if (! $request->has('numero_comprobante') && $request->has('numero_factura')) {
            $request->merge(['numero_comprobante' => $request->input('numero_factura')]);
        }

        $validator = Validator::make($request->all(), [
            'fecha_compra' => 'nullable|date|before_or_equal:today',
            'fecha_vencimiento' => 'nullable|date',
            'fecha_entrega_prevista' => 'nullable|date_format:Y-m-d',
            'numero_comprobante' => 'nullable|string|max:50',
            'items' => 'required|array|min:1|max:100',
        ], [
            'fecha_compra.before_or_equal' => 'La fecha de la compra no puede ser posterior a la fecha actual.',
            'numero_comprobante.max' => 'El número de comprobante no puede superar los 50 caracteres.',
            'items.required' => 'Debe agregar al menos un producto a la compra.',
            'items.min' => 'Debe agregar al menos un producto a la compra.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        $items = $request->input('items');
        $error = $this->validarItems($items);
        if ($error) {
            return response()->json(['status' => $error['status'], 'message' => $error['message']], $error['code']);
        }

        $idUsuario = UsuarioActual::id($request);

        try {
            $compra = DB::transaction(function () use ($compra, $items, $idUsuario, $request) {
                $compra = Compra::query()->whereKey($compra->id_compra)->lockForUpdate()->firstOrFail();
                if ($compra->estado !== 'pendiente') {
                    abort(409, 'La compra dejó de estar pendiente.');
                }
                $importeTotal = 0;
                $nuevos = [];

                foreach ($items as $item) {
                    $cantidad = (int) $item['cantidad'];
                    $precio = (float) $item['precio_unitario'];
                    $subtotal = round($cantidad * $precio, 2);
                    $importeTotal += $subtotal;
                    $nuevos[] = [
                        'id_producto' => (int) $item['id_producto'],
                        'id_unidad' => $this->unidadDelItem($item),
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio,
                        'subtotal' => $subtotal,
                    ];
                }

                $importeTotal = round($importeTotal, 2);
                if ($importeTotal < $compra->totalPagado()) {
                    throw ValidationException::withMessages(['items' => 'El total no puede ser menor que los pagos registrados.']);
                }

                $compra->detalles()->delete();

                foreach ($nuevos as $n) {
                    DetalleCompra::create([
                        'id_compra' => $compra->id_compra,
                        'id_producto' => $n['id_producto'],
                        'id_unidad' => $n['id_unidad'],
                        'cantidad' => $n['cantidad'],
                        'cantidad_recibida' => 0,
                        'precio_unitario' => $n['precio_unitario'],
                        'subtotal' => $n['subtotal'],
                    ]);
                }

                if ($request->filled('fecha_compra')) {
                    $compra->fecha_compra = $request->input('fecha_compra');
                }
                if ($request->has('fecha_entrega_prevista')) {
                    $compra->fecha_entrega_prevista = $request->input('fecha_entrega_prevista') ?: null;
                }
                if ($request->has('fecha_vencimiento')) {
                    $compra->fecha_vencimiento = $request->input('fecha_vencimiento') ?: null;
                }
                if ($request->has('numero_comprobante')) {
                    $compra->numero_comprobante = $request->input('numero_comprobante') ?: null;
                }
                $compra->importe_total = $importeTotal;
                $compra->saldo_pendiente = $importeTotal;
                $compra->fecha_modificacion = Carbon::now();
                $compra->id_usuario_modificacion = $idUsuario;

                $compra->save();

                return $compra;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Compra modificada exitosamente.',
                'data' => $this->formato($compra),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al modificar la compra: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * C05 - Cancelar una compra pendiente.
     */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $purchase = app(\App\Modules\Compras\Services\PurchaseLifecycle::class)->cancel($id, UsuarioActual::id($request));
        return response()->json(['status' => 'success', 'message' => 'Compra anulada con reversión de stock verificada.', 'data' => $this->formato($purchase)]);
    }
    public function cerrarParcial(Request $request, int $id): JsonResponse
    {
        $purchase = app(\App\Modules\Compras\Services\PurchaseLifecycle::class)->closePartial($id, UsuarioActual::id($request));
        return response()->json(['status' => 'success', 'message' => 'Cierre parcial definitivo registrado.', 'data' => $this->formato($purchase)]);
    }
}
