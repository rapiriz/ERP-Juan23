<?php

namespace App\Http\Controllers;

use App\Services\MockClienteApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VentaController extends Controller
{
    private const PRODUCTOS = [
        ['id' => 1, 'codigo' => 'P001', 'nombre' => 'Coca-Cola 1.5L', 'precioMin' => 2500, 'precioMay' => 2200, 'stock' => 48],
        ['id' => 2, 'codigo' => 'P002', 'nombre' => 'Pan lactal grande', 'precioMin' => 1800, 'precioMay' => 1600, 'stock' => 30],
        ['id' => 3, 'codigo' => 'P003', 'nombre' => 'Yerba Mate 1kg', 'precioMin' => 4200, 'precioMay' => 3900, 'stock' => 25],
        ['id' => 4, 'codigo' => 'P004', 'nombre' => 'Aceite girasol 900ml', 'precioMin' => 2100, 'precioMay' => 1950, 'stock' => 60],
        ['id' => 5, 'codigo' => 'P005', 'nombre' => 'Arroz 1kg', 'precioMin' => 1300, 'precioMay' => 1150, 'stock' => 80],
        ['id' => 6, 'codigo' => 'P006', 'nombre' => 'Fideos 500g', 'precioMin' => 950, 'precioMay' => 850, 'stock' => 100],
    ];

    public static function productosSimulados(): array
    {
        return self::PRODUCTOS;
    }

    public function index(MockClienteApi $clientes)
    {
        return view('ventas', [
            'productos' => self::productosSimulados(),
            'clientes' => $clientes->todos(),
        ]);
    }

    public function obtenerProductosSimulados()
    {
        return response()->json(self::productosSimulados());
    }

    public function store(Request $request, MockClienteApi $clientes)
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'lista' => ['required', Rule::in(['minorista', 'mayorista'])],
            'observaciones' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', Rule::in(array_column(self::PRODUCTOS, 'id'))],
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

        $productos = collect(self::productosSimulados())->keyBy('id');
        $lista = $datos['lista'] === 'mayorista' ? 'precioMay' : 'precioMin';
        foreach ($datos['items'] as $indice => $item) {
            $producto = $productos->get((int) $item['id']);
            if ((float) $item['descuento'] > (float) $producto[$lista]) {
                throw ValidationException::withMessages([
                    "items.{$indice}.descuento" => ['El descuento no puede superar el precio unitario actual.'],
                ]);
            }
        }

        $detalles = collect($datos['items'])->map(function (array $item) use ($productos, $lista): array {
            $producto = $productos->get((int) $item['id']);
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
}
