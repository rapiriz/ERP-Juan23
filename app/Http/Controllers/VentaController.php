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

    public function index(MockClienteApi $clientes)
    {
        return view('ventas', [
            'productos' => self::PRODUCTOS,
            'clientes' => $clientes->todos(),
        ]);
    }

    public function store(Request $request, MockClienteApi $clientes)
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'lista' => ['required', Rule::in(['minorista', 'mayorista'])],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', Rule::in(array_column(self::PRODUCTOS, 'id'))],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.descuento' => ['required', 'integer', 'min:0'],
        ]);
        $cliente = $clientes->buscar((int) $datos['cliente_id']);

        if ($cliente === null) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Seleccioná un cliente válido.'],
            ]);
        }

        $productos = collect(self::PRODUCTOS)->keyBy('id');
        $lista = $datos['lista'] === 'mayorista' ? 'precioMay' : 'precioMin';
        foreach ($datos['items'] as $indice => $item) {
            $producto = $productos->get((int) $item['id']);
            if ((int) $item['descuento'] > (int) $producto[$lista]) {
                throw ValidationException::withMessages([
                    "items.{$indice}.descuento" => ['El descuento no puede superar el precio unitario actual.'],
                ]);
            }
        }

        $detalles = collect($datos['items'])->map(function (array $item) use ($productos, $lista): array {
            $producto = $productos->get((int) $item['id']);
            $precio = (int) $producto[$lista];
            $descuento = (int) $item['descuento'];
            $subtotal = (int) $item['cantidad'] * ($precio - $descuento);

            return [
                'id_producto' => $producto['id'],
                'cantidad' => (int) $item['cantidad'],
                'precio_unitario' => $precio,
                'descuento' => $descuento,
                'subtotal' => $subtotal,
            ];
        });
        $total = $detalles->sum('subtotal');

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'items' => ['La venta debe tener un total mayor a cero.'],
            ]);
        }

        $ventaId = DB::transaction(function () use ($cliente, $detalles, $total): int {
            $ventaId = DB::table('venta')->insertGetId([
                'id_cliente' => $cliente['id'],
                'fecha' => now()->toDateString(),
                'total' => $total,
                'numFactura' => 'POS-'.Str::ulid(),
                'estado' => 'confirmada',
                'observaciones' => 'Venta POS a cuenta corriente',
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
            'total' => $total,
        ], 201);
    }
}
