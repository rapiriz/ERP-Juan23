<?php

namespace App\Http\Controllers;

class CuentaCorrienteController extends Controller
{
    public function index()
    {
        $clientes = [
            [
                'id' => 1,
                'nombre' => 'Supermercado El Norte SRL',
                'cuit' => '30-71234567-8',
                'condicion' => 'Responsable Inscripto',
                'domicilio' => 'Av. Mitre 450, Buenos Aires',
                'movimientos' => [
                    ['fecha' => '2026-06-01', 'descripcion' => 'Compra Factura #1045', 'monto' => -8500],
                    ['fecha' => '2026-06-05', 'descripcion' => 'Pago parcial', 'monto' => 5000],
                    ['fecha' => '2026-06-10', 'descripcion' => 'Compra Factura #1062', 'monto' => -11500],
                ],
            ],
            [
                'id' => 2,
                'nombre' => 'Almacén Don Pedro',
                'cuit' => '20-12345678-9',
                'condicion' => 'Consumidor Final',
                'domicilio' => 'Calle 9 123, Pigüé',
                'movimientos' => [
                    ['fecha' => '2026-06-12', 'descripcion' => 'Saldo a favor', 'monto' => 3500],
                ],
            ],
            [
                'id' => 3,
                'nombre' => 'María González',
                'cuit' => '27-98765432-1',
                'condicion' => 'Consumidor Final',
                'domicilio' => 'Buenos Aires',
                'movimientos' => [],
            ],

             [
                'id' => 4,
                'nombre' => 'Tuvi S.A.',
                'cuit' => '27-98762134-1',
                'condicion' => 'Responsable Inscripto',
                'domicilio' => 'Calle X 354, Carhué',
                'movimientos' => [
                    ['fecha' => '2026-06-10', 'descripcion' => 'Compra Factura #2389', 'monto' => -11500],
                    ['fecha' => '2026-06-10', 'descripcion' => 'Compra Factura #3219', 'monto' => -30500],
                    ['fecha' => '2026-06-10', 'descripcion' => 'Pago parcial', 'monto' => 2000]
                ],
            ],
              [
                'id' => 5,
                'nombre' => 'Babau S.A.',
                'cuit' => '27-98700002-8',
                'condicion' => 'Responsable Inscripto',
                'domicilio' => 'Calle 9 de Julio 101, Coronel Pringles',
                'movimientos' => [
                    ['fecha' => '2026-06-10', 'descripcion' => 'Compra Factura #3019', 'monto' => -33450.88],
                    ['fecha' => '2026-06-10', 'descripcion' => 'Pago parcial', 'monto' => 15000],
                ],
            ],

            [
                'id' => 6,
                'nombre' => 'WoW SRL',
                'cuit' => '27-128700002-8',
                'condicion' => 'Responsable Inscripto',
                'domicilio' => 'Calle San Martín 1033, Pigüe',
                'movimientos' => [
                    ['fecha' => '2026-06-10', 'descripcion' => 'Compra Factura #3019', 'monto' => -40450],
                    ['fecha' => '2026-06-10', 'descripcion' => 'Pago parcial', 'monto' => 9000],
                ],
            ],
        ];

        return view('saldo', compact('clientes'));
    }
}
