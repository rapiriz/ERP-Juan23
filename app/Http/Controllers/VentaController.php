<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    public function index()
    {
        // TODO: reemplazar por SELECT real cuando la tabla PRODUCTO tenga datos.
        // Ejemplo de la consulta real (descomentar cuando haya datos):
        //
        // $productos = DB::table('PRODUCTO')
        //     ->where('estado', 'activo')
        //     ->select('id_producto', 'codigo', 'nombre', 'precioMin', 'precioMay', 'stock')
        //     ->get();

        $productos = [
            ['id' => 1, 'codigo' => 'P001', 'nombre' => 'Coca-Cola 1.5L',    'precioMin' => 2500, 'precioMay' => 2200, 'stock' => 48],
            ['id' => 2, 'codigo' => 'P002', 'nombre' => 'Pan lactal grande',  'precioMin' => 1800, 'precioMay' => 1600, 'stock' => 30],
            ['id' => 3, 'codigo' => 'P003', 'nombre' => 'Yerba Mate 1kg',     'precioMin' => 4200, 'precioMay' => 3900, 'stock' => 25],
            ['id' => 4, 'codigo' => 'P004', 'nombre' => 'Aceite girasol 900ml','precioMin' => 2100, 'precioMay' => 1950, 'stock' => 60],
            ['id' => 5, 'codigo' => 'P005', 'nombre' => 'Arroz 1kg',          'precioMin' => 1300, 'precioMay' => 1150, 'stock' => 80],
            ['id' => 6, 'codigo' => 'P006', 'nombre' => 'Fideos 500g',        'precioMin' => 950,  'precioMay' => 850,  'stock' => 100],
        ];

        return view('ventas', compact('productos'));
    }

    // Placeholder para cuando quieras guardar la venta
    public function store(Request $request)
    {
        // TODO: acá va la transacción real con VENTA + DETALLE_VENTA + MOVIMIENTO_STOCK
        return response()->json(['ok' => true, 'mensaje' => 'Venta registrada (placeholder)']);
    }
}
