<?php

namespace App\Http\Controllers;

use App\Services\MockClienteApi;

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
}
