<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\DetalleVenta;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    // ==========================================
    // 1. PRODUCTOS HARDCODEADOS (Modo Mocking API)
    // ==========================================
    public function obtenerProductosSimulados()
    {
        $productos = [
            ['id_producto' => 1, 'codigo' => 'PRD-001', 'nombre' => 'Yerba Mate Playadito 1kg', 'precio' => 3500.00],
            ['id_producto' => 2, 'codigo' => 'PRD-002', 'nombre' => 'Azúcar Ledesma 1kg', 'precio' => 1200.00],
            ['id_producto' => 3, 'codigo' => 'PRD-003', 'nombre' => 'Fideos Matarazzo 500g', 'precio' => 900.00],
            ['id_producto' => 4, 'codigo' => 'PRD-004', 'nombre' => 'Aceite Natura 1.5L', 'precio' => 2800.00]
        ];

        return response()->json($productos, 200);
    }

    // ==========================================
    // 2. GUARDAR VENTA REAL EN MYSQL
    // ==========================================
    public function store(Request $request)
    {
        $carrito = $request->input('productos', []);

        if (empty($carrito)) {
            return response()->json(['mensaje' => 'El carrito está vacío'], 400);
        }

        // Usamos una transacción por seguridad: si falla el detalle, no se guarda la venta vacía
        DB::beginTransaction();

        try {
            // 1. Crear la cabecera de la Venta[cite: 14]
            $venta = Venta::create([
                'fecha'            => date('Y-m-d'), // Formato 'date' que pide tu DB[cite: 14]
                'total'            => $request->input('total', 0),
                // Ajusta este valor al Enum exacto de tu DB (ej: 'pagado', 'completada', 'activa')
                'estado'           => 'completada',
                'id_cliente'       => $request->input('id_cliente', 1), // Harcodeamos cliente 1 por ahora[cite: 14]
                'id_usuario'       => $request->input('id_usuario', 1), // Harcodeamos usuario 1 por ahora
                'numFactura'       => 'FAC-' . rand(1000, 9999), // Simulamos un numero de factura
                'observaciones'    => $request->input('observaciones', 'Venta desde caja'),
                'descuento_global' => $request->input('descuento_global', 0)
            ]);

            // 2. Crear los detalles de la venta[cite: 13]
            foreach ($carrito as $item) {
                DetalleVenta::create([
                    'id_venta'        => $venta->id_venta,
                    'id_producto'     => $item['id_producto'], // Viene de los productos simulados[cite: 13]
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'subtotal'        => $item['cantidad'] * $item['precio'],
                    // id_promocion y descuento pueden quedar nulos por ahora, como indica tu DB[cite: 13]
                ]);
            }

            DB::commit(); // Confirmamos que todo salió bien y guardamos en MySQL

            return response()->json([
                'mensaje' => 'Venta registrada exitosamente en MySQL',
                'venta'   => $venta->load('detalles') // Devolvemos la venta con sus detalles
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack(); // Si hay error, cancelamos todo para no dejar datos a medias
            return response()->json([
                'mensaje' => 'Error al guardar la venta',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 3. OBTENER EL HISTORIAL DE VENTAS
    // ==========================================
    public function index()
    {
        $ventas = Venta::with('detalles')->orderBy('id_venta', 'desc')->get();
        return response()->json($ventas, 200);
    }
}
