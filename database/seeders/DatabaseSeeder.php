<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Usuarios / Repartidores / Admins
        DB::table('USUARIO')->insertOrIgnore([
            ['id_usuario' => 1, 'nombre' => 'Matías Repartidor', 'email' => 'matias@distribuidora.com', 'rol' => 'repartidor', 'estado' => 'activo'],
            ['id_usuario' => 2, 'nombre' => 'Diego Admin', 'email' => 'diego@distribuidora.com', 'rol' => 'admin', 'estado' => 'activo'],
            ['id_usuario' => 3, 'nombre' => 'Estefanía Contable', 'email' => 'estefania@distribuidora.com', 'rol' => 'admin', 'estado' => 'activo'],
        ]);

        // Clientes
        DB::table('CLIENTE')->insertOrIgnore([
            ['id_cliente' => 1, 'nombre' => 'Supermercado Central (Pigüé)', 'estado' => 'activo'],
            ['id_cliente' => 2, 'nombre' => 'Autoservicio El Amigo', 'estado' => 'activo'],
            ['id_cliente' => 3, 'nombre' => 'Despensa San José', 'estado' => 'activo'],
        ]);

        // Ventas / Facturas
        DB::table('VENTA')->insertOrIgnore([
            ['id_venta' => 101, 'id_cliente' => 1, 'fecha' => now()->subDays(2), 'monto_total' => 45500.00, 'pagado' => false],
            ['id_venta' => 102, 'id_cliente' => 2, 'fecha' => now()->subDays(1), 'monto_total' => 28000.00, 'pagado' => false],
            ['id_venta' => 103, 'id_cliente' => 3, 'fecha' => now(), 'monto_total' => 15200.00, 'pagado' => false],
        ]);

        // Entregas
        DB::table('ENTREGA')->insertOrIgnore([
            ['id_entrega' => 1, 'id_repartidor' => 1, 'estado' => 'en_camino'],
            ['id_entrega' => 2, 'id_repartidor' => 1, 'estado' => 'finalizada'],
        ]);

        // Rendiciones preexistentes individuales por medio de pago
        DB::table('RENDICION')->insertOrIgnore([
            [
                'id_rendicion' => 1,
                'id_repartidor' => 1,
                'id_entrega' => 2,
                'fecha' => now()->subDay(),
                'total_rendido' => 45500.00,
                'estado' => 'aprobada',
                'observaciones' => 'Cobro en Efectivo — Supermercado Central (#101). Impactado en Caja.',
                'id_usuario_validador' => 3,
                'fecha_validacion' => now()->subHours(12),
                'motivo_rechazo' => null,
            ],
            [
                'id_rendicion' => 2,
                'id_repartidor' => 1,
                'id_entrega' => 2,
                'fecha' => now()->subDay(),
                'total_rendido' => 28000.00,
                'estado' => 'aprobada',
                'observaciones' => 'Transferencia bancaria — Autoservicio El Amigo (#102). Conciliación pendiente.',
                'id_usuario_validador' => 3,
                'fecha_validacion' => now()->subHours(10),
                'motivo_rechazo' => null,
            ],
            [
                'id_rendicion' => 3,
                'id_repartidor' => 1,
                'id_entrega' => 1,
                'fecha' => now(),
                'total_rendido' => 15200.00,
                'estado' => 'pendiente',
                'observaciones' => 'Cobro en Efectivo — Despensa San José (#103). Pendiente de arqueo.',
                'id_usuario_validador' => null,
                'fecha_validacion' => null,
                'motivo_rechazo' => null,
            ],
            [
                'id_rendicion' => 4,
                'id_repartidor' => 2,
                'id_entrega' => 1,
                'fecha' => now(),
                'total_rendido' => 50000.00,
                'estado' => 'rechazada',
                'observaciones' => 'Transferencia declarada por Juan Pérez.',
                'id_usuario_validador' => 3,
                'fecha_validacion' => now()->subHours(1),
                'motivo_rechazo' => 'No se encontró el comprobante de transferencia en el homebanking.',
            ]
        ]);

        DB::table('RENDICION_COBRO')->insertOrIgnore([
            ['id_rendicion' => 1, 'id_cliente' => 1, 'id_factura' => 101, 'monto' => 45500.00, 'medio_pago' => 'efectivo', 'fecha_registro' => now()->subDay()],
            ['id_rendicion' => 2, 'id_cliente' => 2, 'id_factura' => 102, 'monto' => 28000.00, 'medio_pago' => 'transferencia', 'fecha_registro' => now()->subDay()],
            ['id_rendicion' => 3, 'id_cliente' => 3, 'id_factura' => 103, 'monto' => 15200.00, 'medio_pago' => 'efectivo', 'fecha_registro' => now()],
            ['id_rendicion' => 4, 'id_cliente' => 2, 'id_factura' => 102, 'monto' => 50000.00, 'medio_pago' => 'transferencia', 'fecha_registro' => now()],
        ]);
    }
}
