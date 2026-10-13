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
        // 1. Zonas de reparto
        DB::table('zona')->upsert([
            ['id_zona' => 1, 'nombre' => 'Ruta Saavedra', 'descripcion' => 'Localidades de Saavedra y Goyena'],
            ['id_zona' => 2, 'nombre' => 'Ruta Tornquist', 'descripcion' => 'Tornquist, Sierra de la Ventana'],
            ['id_zona' => 3, 'nombre' => 'Pigüé Urbano', 'descripcion' => 'Casco céntrico y comercios locales'],
        ], ['id_zona'], ['nombre', 'descripcion']);

        // 2. Usuarios / Repartidores / Admins
        DB::table('USUARIO')->upsert([
            ['id_usuario' => 1, 'nombre' => 'Matías Repartidor', 'user' => 'matias', 'email' => 'matias@distribuidora.com', 'rol' => 'repartidor', 'estado' => 'activo'],
            ['id_usuario' => 2, 'nombre' => 'Diego Admin', 'user' => 'diego', 'email' => 'diego@distribuidora.com', 'rol' => 'admin', 'estado' => 'activo'],
            ['id_usuario' => 3, 'nombre' => 'Estefanía Contable', 'user' => 'estefania', 'email' => 'estefania@distribuidora.com', 'rol' => 'admin', 'estado' => 'activo'],
            ['id_usuario' => 4, 'nombre' => 'Lucas Repartidor', 'user' => 'lucas', 'email' => 'lucas@distribuidora.com', 'rol' => 'repartidor', 'estado' => 'activo'],
        ], ['id_usuario'], ['nombre', 'user', 'email', 'rol', 'estado']);

        // 3. Clientes con asignación de Zona y Domicilio
        DB::table('CLIENTE')->upsert([
            [
                'id_cliente' => 1,
                'nombre' => 'Supermercado Central (Pigüé)',
                'razon_social' => 'Super Central S.R.L.',
                'cuit' => '30-11223344-5',
                'direccion' => 'Av. Casey 450',
                'localidad' => 'Pigüé',
                'telefono' => '02923-471122',
                'id_zona' => 1,
                'estado' => 'activo'
            ],
            [
                'id_cliente' => 2,
                'nombre' => 'Autoservicio El Amigo',
                'razon_social' => 'El Amigo S.A.',
                'cuit' => '30-22334455-6',
                'direccion' => 'San Martín 120',
                'localidad' => 'Saavedra',
                'telefono' => '02923-451122',
                'id_zona' => 1,
                'estado' => 'activo'
            ],
            [
                'id_cliente' => 3,
                'nombre' => 'Despensa San José',
                'razon_social' => 'San José Distribución',
                'cuit' => '30-33445566-7',
                'direccion' => 'Belgrano 890',
                'localidad' => 'Tornquist',
                'telefono' => '0291-4940112',
                'id_zona' => 2,
                'estado' => 'activo'
            ],
        ], ['id_cliente'], ['nombre', 'razon_social', 'cuit', 'direccion', 'localidad', 'telefono', 'id_zona', 'estado']);

        // 4. Catálogo de Productos
        DB::table('producto')->upsert([
            ['id_producto' => 1, 'codigo' => 'ACE-GIR-01', 'nombre' => 'Aceite Girasol 1.5L (Caja x 12)', 'descripcion' => 'Caja con 12 botellas de 1.5L', 'precio_unitario' => 18500.00, 'bultos_por_unidad' => 1, 'stock' => 150, 'estado' => 'activo'],
            ['id_producto' => 2, 'codigo' => 'HAR-000-01', 'nombre' => 'Harina 000 1kg (Fardo x 10)', 'descripcion' => 'Fardo de 10 paquetes de 1kg', 'precio_unitario' => 9800.00, 'bultos_por_unidad' => 1, 'stock' => 300, 'estado' => 'activo'],
            ['id_producto' => 3, 'codigo' => 'AZU-LED-01', 'nombre' => 'Azúcar Ledesma 1kg (Fardo x 10)', 'descripcion' => 'Fardo de 10 paquetes de 1kg', 'precio_unitario' => 12500.00, 'bultos_por_unidad' => 1, 'stock' => 200, 'estado' => 'activo'],
        ], ['id_producto'], ['codigo', 'nombre', 'descripcion', 'precio_unitario', 'bultos_por_unidad', 'stock', 'estado']);

        // 5. Ventas / Facturas con datos de despacho
        DB::table('VENTA')->upsert([
            [
                'id_venta' => 101,
                'id_cliente' => 1,
                'id_usuario' => 1,
                'fecha' => now()->subDays(2),
                'monto_total' => 45500.00,
                'total' => 45500.00,
                'total_bultos' => 10,
                'total_sueltos' => 2,
                'estado' => 'confirmada',
                'observaciones' => 'Entrega en horario comercial. Cobro en efectivo.',
                'pagado' => false
            ],
            [
                'id_venta' => 102,
                'id_cliente' => 2,
                'id_usuario' => 1,
                'fecha' => now()->subDays(1),
                'monto_total' => 28000.00,
                'total' => 28000.00,
                'total_bultos' => 6,
                'total_sueltos' => 0,
                'estado' => 'confirmada',
                'observaciones' => 'Descarga por portón trasero.',
                'pagado' => false
            ],
            [
                'id_venta' => 103,
                'id_cliente' => 3,
                'id_usuario' => 1,
                'fecha' => now(),
                'monto_total' => 15200.00,
                'total' => 15200.00,
                'total_bultos' => 3,
                'total_sueltos' => 1,
                'estado' => 'confirmada',
                'observaciones' => 'Local céntrico.',
                'pagado' => false
            ],
        ], ['id_venta'], ['id_cliente', 'id_usuario', 'fecha', 'monto_total', 'total', 'total_bultos', 'total_sueltos', 'estado', 'observaciones', 'pagado']);

        // 6. Detalle de Ventas para Venta #101 (requerido para armar Remito)
        DB::table('detalle_venta')->upsert([
            ['id_detalle_venta' => 1, 'id_venta' => 101, 'id_producto' => 1, 'cantidad' => 2, 'precio_unitario' => 18500.00, 'subtotal' => 37000.00, 'bultos' => 2, 'unidades_sueltas' => 0],
            ['id_detalle_venta' => 2, 'id_venta' => 101, 'id_producto' => 2, 'cantidad' => 1, 'precio_unitario' => 8500.00, 'subtotal' => 8500.00, 'bultos' => 1, 'unidades_sueltas' => 2],
        ], ['id_detalle_venta'], ['id_venta', 'id_producto', 'cantidad', 'precio_unitario', 'subtotal', 'bultos', 'unidades_sueltas']);

        // 7. Entregas base
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
