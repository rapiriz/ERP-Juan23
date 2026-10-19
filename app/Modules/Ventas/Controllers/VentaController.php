<?php

namespace App\Modules\Ventas\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MockProductoApi;
use App\Services\MockClienteApi;
use App\Services\MockUsuarioApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{

    public function index(MockClienteApi $clientes, MockProductoApi $productos)
    {
        return view('ventas', [
            'productos' => $productos->todos(),
            'clientes' => $clientes->todos(),
        ]);
    }

    public function obtenerProductosSimulados(MockProductoApi $productos)
    {
         return response()->json($productos->todos());
    }

    public function store(Request $request, MockClienteApi $clientes, MockProductoApi $productos)
    {
        $todosLosProductos = $productos->todos();
        $datos = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'lista' => ['required', Rule::in(['minorista', 'mayorista'])],
            'observaciones' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', Rule::in(array_column($todosLosProductos, 'id'))],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.descuento' => ['required', 'numeric', 'min:0'],
            'descuento_global' => ['sometimes', 'array'],
            'descuento_global.modo' => ['required_with:descuento_global', Rule::in(['porcentaje', 'monto'])],
            'descuento_global.valor' => ['required_with:descuento_global', 'numeric', 'min:0'],
        ]);
        $cliente = $clientes->buscar((int) $datos['cliente_id']);

        if ($cliente === null) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Seleccioná un cliente válido.'],
            ]);
        }
        $productosIndexados = collect($todosLosProductos)->keyBy('id');
        $lista = $datos['lista'] === 'mayorista' ? 'precioMay' : 'precioMin';
        foreach ($datos['items'] as $indice => $item) {
            $producto = $productosIndexados->get((int) $item['id']);
            if ((float) $item['descuento'] > (float) $producto[$lista]) {
                throw ValidationException::withMessages([
                    "items.{$indice}.descuento" => ['El descuento no puede superar el precio unitario actual.'],
                ]);
            }
        }

        $detalles = collect($datos['items'])->map(function (array $item) use ($productosIndexados, $lista): array {
            $producto = $productosIndexados->get((int) $item['id']);
            $precio = (int) $producto[$lista];
            $descuento = round((float) $item['descuento'], 2);
            $subtotal = round((int) $item['cantidad'] * ($precio - $descuento), 2);

            return [
                'id_producto' => $producto['id'],
                'cantidad' => (int) $item['cantidad'],
                'precio_unitario' => $precio,
                'descuento' => $descuento,
                'subtotal' => $subtotal,
            ];
        });
        $subtotalConDescuentosDeLinea = round($detalles->sum('subtotal'), 2);
        $descuentoGlobal = $datos['descuento_global'] ?? ['modo' => 'monto', 'valor' => 0];
        $valorDescuentoGlobal = (float) $descuentoGlobal['valor'];

        if ($descuentoGlobal['modo'] === 'porcentaje' && $valorDescuentoGlobal > 100) {
            throw ValidationException::withMessages([
                'descuento_global.valor' => ['El porcentaje de descuento global debe estar entre 0 y 100.'],
            ]);
        }

        $montoDescuentoGlobal = $descuentoGlobal['modo'] === 'porcentaje'
            ? round($subtotalConDescuentosDeLinea * $valorDescuentoGlobal / 100, 2)
            : round(min($valorDescuentoGlobal, $subtotalConDescuentosDeLinea), 2);
        $total = round($subtotalConDescuentosDeLinea - $montoDescuentoGlobal, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'items' => ['La venta debe tener un total mayor a cero.'],
            ]);
        }

        $observaciones = trim($datos['observaciones'] ?? '') ?: 'Venta POS a cuenta corriente';

        $ventaId = DB::transaction(function () use ($cliente, $detalles, $total, $montoDescuentoGlobal, $observaciones): int {
            $ventaId = DB::table('venta')->insertGetId([
                'id_cliente' => $cliente['id'],
                'fecha' => now()->toDateString(),
                'total' => $total,
                'descuento_global' => $montoDescuentoGlobal,
                'numFactura' => 'POS-'.Str::ulid(),
                'estado' => 'confirmada',
                'observaciones' => $observaciones,
                'id_usuario' => config('cuenta_corriente.id_usuario_prueba'),
            ], 'id_venta');

            foreach ($detalles as $detalle) {
                DB::table('detalle_venta')->insert([
                    'id_venta' => $ventaId,
                    'id_producto' => $detalle['id_producto'],
                    'id_promocion' => null,
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

            return (int) $ventaId;
        });

        return response()->json([
            'mensaje' => 'Venta registrada y cargada a la cuenta corriente.',
            'venta_id' => $ventaId,
            'cliente_id' => $cliente['id'],
            'descuento_global' => $montoDescuentoGlobal,
            'total' => $total,
        ], 201);
    }
    public function historial(Request $request, MockClienteApi $clientes, MockUsuarioApi $usuarios, MockProductoApi $productos)
{
    // ---------------------------------------------------------------
    // 1) Filtros
    // ---------------------------------------------------------------
    $filtros = [
        'texto'    => trim((string) $request->query('texto', '')),
        'cliente'  => $request->query('cliente'),
        'producto' => $request->query('producto'),
        'desde'    => $request->query('desde'),
        'hasta'    => $request->query('hasta'),
    ];

    // ---------------------------------------------------------------
    // 2) Query principal: SOLO venta + usuario
    //    (no joineamos con cliente porque es responsabilidad de otro módulo)
    // ---------------------------------------------------------------
    $query = DB::table('venta as v')
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
        ->orderByDesc('v.id_venta');

    if ($filtros['texto'] !== '') {
        $query->where(function ($q) use ($filtros) {
            $q->where('v.numFactura', 'like', "%{$filtros['texto']}%")
              ->orWhere('v.observaciones', 'like', "%{$filtros['texto']}%");
        });
    }

    if (!empty($filtros['cliente'])) {
        $query->where('v.id_cliente', $filtros['cliente']);
    }

    if (!empty($filtros['desde'])) {
        $query->whereDate('v.fecha', '>=', $filtros['desde']);
    }
    if (!empty($filtros['hasta'])) {
        $query->whereDate('v.fecha', '<=', $filtros['hasta']);
    }

    $ventas = $query->get();

    // ---------------------------------------------------------------
    // 3) Índice de clientes mock (id => datos)
    //    Traemos todos UNA vez y los consultamos en memoria.
    // ---------------------------------------------------------------
    $clientesMock = collect($clientes->todos())->keyBy('id');

    // ---------------------------------------------------------------
    // 4) Detalle de todas las ventas en una sola query
    // ---------------------------------------------------------------
    $idsVentas = $ventas->pluck('id_venta')->all();

    $detalles = collect();
    if (!empty($idsVentas)) {
        $detalles = DB::table('detalle_venta as dv')
        ->whereIn('dv.id_venta', $idsVentas)
        ->select(
            'dv.id_venta',
            'dv.id_producto',
            'dv.cantidad',
            'dv.precio_unitario',
            'dv.descuento',
            'dv.subtotal'
        )
        ->get()
        ->groupBy('id_venta');
    }

    // ---------------------------------------------------------------
    // 5) Método de pago (si existe tabla cobro_venta / cobro)
    //    Envolvemos en try/catch por si esas tablas todavía no existen.
    // ---------------------------------------------------------------
    $metodosPago = collect();
    if (!empty($idsVentas)) {
        try {
            $metodosPago = DB::table('cobro_venta as cv')
                ->join('cobro as c', 'c.id_cobro', '=', 'cv.id_cobro')
                ->whereIn('cv.id_venta', $idsVentas)
                ->select('cv.id_venta', 'c.medio_pago')
                ->get()
                ->groupBy('id_venta')
                ->map(fn ($g) => $g->pluck('medio_pago')->unique()->values()->all());
        } catch (\Throwable $e) {
            // Si las tablas no existen todavía, todas las ventas caen como CC
            $metodosPago = collect();
        }
    }

    // ---------------------------------------------------------------
    // 6) Armar estructura final cruzando con cliente mock
    // ---------------------------------------------------------------
    $ventas = $ventas->map(function ($v) use ($detalles, $metodosPago, $clientesMock, $usuarios, $productos) {
        $clienteMock = $clientesMock->get($v->id_cliente);
        $productosMock = $productos->indexado();
        $nombreCliente = $clienteMock['nombre'] ?? "Cliente #{$v->id_cliente}";
        $nombreUsuario = $usuarios->nombre((int) $v->id_usuario);
        $medios = $metodosPago->get($v->id_venta, []);
        $esCC   = empty($medios);

        return [
            'id'            => $v->id_venta,
            'factura'       => $v->factura ?? ('#' . $v->id_venta),
            'fecha'         => $v->fecha,
            'fecha_iso'     => $v->fecha,
            'cliente'       => $nombreCliente,
            'total'         => (float) $v->total,
            'descuento'     => (float) ($v->descuento ?? 0),
            'estado'        => $v->estado ?? 'pendiente',
            'observaciones' => $v->observaciones,
            'usuario'       => $nombreUsuario,
            'metodo'        => $esCC ? 'Cuenta Corriente' : ucfirst(str_replace('_', ' ', $medios[0])),
            'cc'            => $esCC,
            // Flags para los botones Modificar / Eliminar / Restaurar y para mostrar la venta en gris si está anulada.
            // Reglas en app/Modules/Ventas/Enums/EstadoVenta.php → agrega: anulada, puede_modificar, puede_eliminar, puede_restaurar.
            ...\App\Modules\Ventas\Enums\EstadoVenta::accionesPara($v->estado),
            'detalle' =>($detalles->get($v->id_venta) ?? collect())->map(function ($d) use ($productosMock) {
                $prod = $productosMock[$d->id_producto] ?? null;

                return [
                    'codigo'          => $prod['codigo'] ?? '',
                    'producto'        => $prod['nombre'] ?? "Producto #{$d->id_producto}",
                    'cantidad'        => (int) $d->cantidad,
                    'precio_unitario' => (float) $d->precio_unitario,
                    'descuento'       => (float) $d->descuento,
                    'subtotal'        => (float) $d->subtotal,
                ];
            })->all(),
        ];
    });

    // ---------------------------------------------------------------
    // 7) KPIs
    // ---------------------------------------------------------------
    $kpis = [
        'total_vendido' => $ventas->sum('total'),
        'descuentos'    => $ventas->sum('descuento'),
        'en_cc'         => $ventas->where('cc', true)->sum('total'),
        'cantidad'      => $ventas->count(),
    ];

    // ---------------------------------------------------------------
    // 8) Listas para los selects de filtros
    //    - Clientes: vienen del mock
    //    - Productos: sí salen de la BD porque es parte de tu módulo
    // ---------------------------------------------------------------
    $clientesFiltro = collect($clientes->todos())
        ->map(fn ($c) => ['id' => $c['id'], 'nombre' => $c['nombre']])
        ->values();

    $productosFiltro = collect($productos->todos())
        ->map(fn ($p) => ['id_producto' => $p['id'], 'nombre' => $p['nombre']])
        ->values();

    return view('historial-ventas', compact(
        'ventas', 'kpis', 'clientesFiltro', 'productosFiltro', 'filtros'
    ));
}
}
