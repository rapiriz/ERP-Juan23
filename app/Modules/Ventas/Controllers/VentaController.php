<?php

namespace App\Modules\Ventas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Promociones\Services\PromotionSaleService;
use App\Modules\Shared\Services\UsuarioService;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Enums\EstadoVenta;
use App\Modules\Ventas\Services\CalculadoraVenta;
use App\Modules\Ventas\Services\SaleCreationRequests;
use App\Modules\Ventas\Services\StockDeliveryService;
use App\Support\UsuarioActual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    public function index(ClienteGateway $clientes, ProductoGateway $productos, StockGateway $stock)
    {
        $productosDisponibles = collect($productos->todos())->map(function (array $producto) use ($stock): array {
            $disponible = $stock->disponible((int) $producto['id']);
            if ($disponible !== null) {
                $producto['stock'] = $disponible;
            }

            return $producto;
        })->all();

        return view('ventas.ventas', [
            'productos' => $productosDisponibles,
            'clientes' => $clientes->todos(),
        ]);
    }

    public function obtenerProductosSimulados(ProductoGateway $productos, StockGateway $stock)
    {
        $catalogo = collect($productos->todos())->map(function (array $producto) use ($stock): array {
            $disponible = $stock->disponible((int) $producto['id']);
            if ($disponible !== null) {
                $producto['stock'] = $disponible;
            }

            return $producto;
        })->values()->all();

        return response()->json($catalogo);
    }

    public function store(Request $request, ClienteGateway $clientes, ProductoGateway $productos, StockGateway $stock)
    {
        if ($request->header('Idempotency-Key')) {
            abort_if($request->filled('request_key') && $request->input('request_key') !== $request->header('Idempotency-Key'), 422, 'Las referencias de solicitud no coinciden.');
            $request->merge(['request_key' => $request->header('Idempotency-Key')]);
        }
        $datos = $request->validate([
            'request_key' => ['nullable', 'uuid'],
            'cliente_id' => ['required', 'integer'],
            'lista' => ['required', Rule::in(['minorista', 'mayorista'])],
            'observaciones' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.descuento' => ['required', 'numeric', 'min:0'],
            'items.*.id_promocion' => ['nullable', 'integer', 'min:1'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'descuento_global' => ['sometimes', 'array'],
            'descuento_global.modo' => ['required_with:descuento_global', Rule::in(['porcentaje', 'monto'])],
            'descuento_global.valor' => ['required_with:descuento_global', 'numeric', 'min:0'],
        ]);
        $requestKey = $datos['request_key'] ?? (string) Str::uuid();
        $previous = app(SaleCreationRequests::class)->replay($requestKey, UsuarioActual::id($request), $datos);
        if ($previous !== null) {
            return $this->saleResponse([...$previous, 'repetido' => true], (int) $datos['cliente_id']);
        }
        $todosLosProductos = $productos->todos();
        $cliente = $clientes->buscar((int) $datos['cliente_id']);

        if ($cliente === null) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Seleccioná un cliente válido.'],
            ]);
        }
        $catalogo = collect($todosLosProductos)->keyBy('id')->all();
        $observaciones = trim($datos['observaciones'] ?? '') ?: 'Venta POS a cuenta corriente';

        $resultado = DB::transaction(function () use ($request, $cliente, $catalogo, $datos, $observaciones, $requestKey): array {
            DB::table('clientes')->where('id', $cliente['id'])->lockForUpdate()->firstOrFail();
            $previous = app(SaleCreationRequests::class)->claim($requestKey, UsuarioActual::id($request), $datos);
            if ($previous !== null) {
                return [...$previous, 'repetido' => true];
            }
            app(PromotionSaleService::class)->validate($datos['items'], $catalogo, $datos['lista'] === 'mayorista' ? 'precioMay' : 'precioMin');
            $calculo = app(CalculadoraVenta::class)->calcular(
                $datos['items'], $catalogo, $datos['lista'], $datos['descuento_global'] ?? null);
            $detalles = $calculo['detalles'];
            $total = $calculo['total'];
            $montoDescuentoGlobal = $calculo['descuento_global'];
            $cantidadesPorProducto = [];
            foreach ($detalles as $detalle) {
                $pid = (int) $detalle['id_producto'];
                $cantidadesPorProducto[$pid] = ($cantidadesPorProducto[$pid] ?? 0) + (int) $detalle['cantidad'];
            }
            $ventaId = DB::table('venta')->insertGetId([
                'id_cliente' => $cliente['id'],
                'fecha' => now()->toDateString(),
                'total' => $total,
                'descuento_global' => $montoDescuentoGlobal,
                'numFactura' => 'POS-'.Str::ulid(),
                'estado' => 'pendiente',
                'observaciones' => $observaciones,
                'id_usuario' => UsuarioActual::id($request),
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
            ], 'id_venta');

            foreach ($detalles as $detalle) {
                DB::table('detalle_venta')->insert([
                    'id_venta' => $ventaId,
                    'id_producto' => $detalle['id_producto'],
                    'id_promocion' => $detalle['id_promocion'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento' => $detalle['descuento'],
                    'subtotal' => $detalle['subtotal'],
                ]);
            }

            DB::table('cuenta_corriente_movimientos')->insert([
                'cliente_id' => $cliente['id'],
                'venta_id' => $ventaId,
                'tipo' => 'venta',
                'descripcion' => "Venta POS #{$ventaId}",
                'importe' => -$total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cambiosStock = [];
            foreach ($cantidadesPorProducto as $idProducto => $cantidad) {
                $cambiosStock[(int) $idProducto] = -((int) $cantidad);
            }
            $operation = app(StockDeliveryService::class)->enqueue(
                (int) $ventaId, $cambiosStock, 'venta', UsuarioActual::id($request));

            $response = ['venta_id' => (int) $ventaId, 'total' => $total, 'descuento_global' => $montoDescuentoGlobal, 'stock_operation_id' => $operation, 'request_key' => $requestKey];
            app(SaleCreationRequests::class)->complete($requestKey, $response);

            return [...$response, 'repetido' => false];
        });

        return $this->saleResponse($resultado, (int) $cliente['id']);
    }

    private function saleResponse(array $resultado, int $clientId)
    {
        $operation = $resultado['stock_operation_id'];
        $applied = $operation === null || app(StockDeliveryService::class)->deliver($operation);

        return response()->json([
            ...$resultado,
            'stock_estado' => $applied ? 'aplicado' : 'pendiente',
            'mensaje' => 'Venta registrada y cargada a la cuenta corriente.',
            'cliente_id' => $clientId,
        ], $resultado['repetido'] ? 200 : 201);
    }

    public function historial(ClienteGateway $clientes, ProductoGateway $productos)
    {
        $ventas = DB::table('venta as v')
            ->select(
                'v.id_venta',
                'v.id_cliente',
                'v.numFactura as factura',
                'v.fecha',
                'v.total',
                'v.descuento_global as descuento',
                'v.estado',
                'v.observaciones',
                'v.id_usuario'
            )
            ->orderByDesc('v.fecha')
            ->orderByDesc('v.id_venta')
            ->get();

        $clientesPorId = collect($clientes->todos())->keyBy('id');
        $productosPorId = collect($productos->todos())->keyBy('id');
        $idsVentas = $ventas->pluck('id_venta')->all();
        $detalles = empty($idsVentas)
            ? collect()
            : DB::table('detalle_venta as dv')
                ->whereIn('dv.id_venta', $idsVentas)
                ->select(
                    'dv.id_venta',
                    'dv.id_producto',
                    'dv.id_promocion',
                    'dv.cantidad',
                    'dv.precio_unitario',
                    'dv.descuento',
                    'dv.subtotal'
                )
                ->get()
                ->groupBy('id_venta');

        $metodosPago = collect();

        if (! empty($idsVentas)) {
            try {
                $metodosPago = DB::table('cobro_venta as cv')
                    ->join('cobro as c', 'c.id_cobro', '=', 'cv.id_cobro')
                    ->whereIn('cv.id_venta', $idsVentas)
                    ->select('cv.id_venta', 'c.medio_pago')
                    ->get()
                    ->groupBy('id_venta')
                    ->map(fn ($grupo) => $grupo->pluck('medio_pago')->unique()->values()->all());
            } catch (\Throwable $e) {
                $metodosPago = collect();
            }
        }

        $ventas = $ventas->map(function ($venta) use (
            $detalles,
            $metodosPago,
            $clientesPorId,
            $productosPorId
        ) {
            $cliente = $clientesPorId->get($venta->id_cliente);

            $detalle = ($detalles->get($venta->id_venta) ?? collect())
                ->map(function ($item) use ($productosPorId) {
                    $producto = $productosPorId->get($item->id_producto);
                    $idPromocion = property_exists($item, 'id_promocion') ? $item->id_promocion : null;
                    $promocion = $idPromocion
                        ? DB::table('promocion')->where('id_promocion', $item->id_promocion)->value('nombre')
                        : null;

                    return [
                        'id_producto' => (int) $item->id_producto,
                        'id_promocion' => $idPromocion ? (int) $idPromocion : null,
                        'promocion' => $promocion,
                        'codigo' => $producto['codigo'] ?? '',
                        'producto' => $producto['nombre'] ?? "Producto #{$item->id_producto}",
                        'cantidad' => (int) $item->cantidad,
                        'precio_unitario' => (float) $item->precio_unitario,
                        'descuento' => (float) $item->descuento,
                        'subtotal' => (float) $item->subtotal,
                    ];
                })->all();

            $medios = $metodosPago->get($venta->id_venta, []);
            $esCC = empty($medios);
            $acciones = EstadoVenta::accionesPara($venta->estado);
            if (DB::table('entrega_pedido')->where('id_venta', $venta->id_venta)->exists() || DB::table('g4_devoluciones')->where('venta_id', $venta->id_venta)->exists()) {
                $acciones = array_replace($acciones, ['puede_modificar' => false, 'puede_eliminar' => false, 'puede_restaurar' => false]);
            }

            return [
                'id' => (int) $venta->id_venta,
                'cliente_id' => (int) $venta->id_cliente,
                'factura' => $venta->factura ?? ('#'.$venta->id_venta),
                'fecha' => $venta->fecha,
                'fecha_iso' => substr((string) $venta->fecha, 0, 10),
                'cliente' => $cliente['nombre'] ?? "Cliente #{$venta->id_cliente}",
                'localidad' => $cliente['localidad'] ?? '',
                'total' => (float) $venta->total,
                'descuento' => (float) ($venta->descuento ?? 0)
                    + collect($detalle)->sum(fn ($item) => $item['descuento'] * $item['cantidad']),
                'estado' => $venta->estado ?? 'pendiente',
                'observaciones' => $venta->observaciones,
                'usuario' => UsuarioService::obtenerNombre((int) $venta->id_usuario),
                'metodo' => $esCC
                    ? 'Cuenta Corriente'
                    : ucfirst(str_replace('_', ' ', $medios[0])),
                'cc' => $esCC,

                // Conserva las acciones que ya tenía develop-g4.
                ...$acciones,

                'detalle' => $detalle,
            ];
        })->values();

        $kpis = [
            'total_vendido' => $ventas->sum('total'),
            'descuentos' => $ventas->sum('descuento'),
            'en_cc' => $ventas->where('cc', true)->sum('total'),
            'cantidad' => $ventas->count(),
        ];
        $clientesFiltro = collect($clientes->todos())
            ->map(fn ($cliente) => ['id' => $cliente['id'], 'nombre' => $cliente['nombre']])
            ->values();
        $productosFiltro = collect($productos->todos())
            ->map(fn ($producto) => ['id_producto' => $producto['id'], 'nombre' => $producto['nombre']])
            ->values();

        return view('historial-ventas.historial-ventas', compact(
            'ventas', 'kpis', 'clientesFiltro', 'productosFiltro'
        ));
    }
}
