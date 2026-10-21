<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Enfoque principal: Entidades propias del Grupo 2 (Caja, Entregas, Rendiciones).
     */
    public function run(): void
    {
        // ================================================================
        // 0. USUARIOS DEL SISTEMA (Transversal / Autenticación)
        // ================================================================
        if (Schema::hasTable('USUARIO')) {
            DB::table('USUARIO')->upsert([
                ['id_usuario' => 1, 'nombre' => 'Matías Repartidor', 'user' => 'matias', 'email' => 'matias@distribuidora.com', 'rol' => 'repartidor', 'estado' => 'activo'],
                ['id_usuario' => 2, 'nombre' => 'Diego Admin', 'user' => 'diego', 'email' => 'diego@distribuidora.com', 'rol' => 'admin', 'estado' => 'activo'],
                ['id_usuario' => 3, 'nombre' => 'Estefanía Contable', 'user' => 'estefania', 'email' => 'estefania@distribuidora.com', 'rol' => 'admin', 'estado' => 'activo'],
                ['id_usuario' => 4, 'nombre' => 'Lucas Repartidor', 'user' => 'lucas', 'email' => 'lucas@distribuidora.com', 'rol' => 'repartidor', 'estado' => 'activo'],
            ], ['id_usuario'], ['nombre', 'user', 'email', 'rol', 'estado']);
        }

        // ================================================================
        // 1. MÓDULO ENTREGAS / LOGÍSTICA (G2)
        // ================================================================

        // 1.1 Zonas de reparto logístico
        if (Schema::hasTable('zona')) {
            DB::table('zona')->upsert([
                ['id_zona' => 1, 'nombre' => 'Ruta Saavedra', 'descripcion' => 'Localidades de Saavedra y Goyena'],
                ['id_zona' => 2, 'nombre' => 'Ruta Tornquist', 'descripcion' => 'Tornquist, Sierra de la Ventana'],
                ['id_zona' => 3, 'nombre' => 'Pigüé Urbano', 'descripcion' => 'Casco céntrico y comercios locales'],
            ], ['id_zona'], ['nombre', 'descripcion']);
        }

        // 1.2 Entregas base (Hojas de ruta)
        if (Schema::hasTable('ENTREGA')) {
            DB::table('ENTREGA')->upsert([
                [
                    'id_entrega' => 1,
                    'id_repartidor' => 1,
                    'fecha_salida' => now()->toDateString(),
                    'estado' => 'en_camino',
                    'observaciones' => 'Reparto en curso. Pendiente de rendición.',
                    'id_usuario_creacion' => 2,
                ],
                [
                    'id_entrega' => 2,
                    'id_repartidor' => 1,
                    'fecha_salida' => now()->subDay()->toDateString(),
                    'estado' => 'finalizada',
                    'observaciones' => 'Reparto completado y rendido exitosamente.',
                    'id_usuario_creacion' => 2,
                ],
            ], ['id_entrega'], ['id_repartidor', 'fecha_salida', 'estado', 'observaciones', 'id_usuario_creacion']);
        }

        // 1.3 Entrega - Pedidos (Asociación con pedidos de Ventas)
        if (Schema::hasTable('entrega_pedido')) {
            DB::table('entrega_pedido')->upsert([
                [
                    'id_entrega_pedido' => 1,
                    'id_entrega' => 1,
                    'id_venta' => 103, // Despensa San José ($15.200)
                    'estado_pedido_entrega' => 'entregado',
                    'motivo_rechazo' => null,
                    'fecha_actualizacion' => now(),
                ],
                [
                    'id_entrega_pedido' => 2,
                    'id_entrega' => 2,
                    'id_venta' => 101, // Supermercado Central ($45.500)
                    'estado_pedido_entrega' => 'entregado',
                    'motivo_rechazo' => null,
                    'fecha_actualizacion' => now()->subDay(),
                ],
                [
                    'id_entrega_pedido' => 3,
                    'id_entrega' => 2,
                    'id_venta' => 102, // Autoservicio El Amigo ($28.000)
                    'estado_pedido_entrega' => 'entregado',
                    'motivo_rechazo' => null,
                    'fecha_actualizacion' => now()->subDay(),
                ],
            ], ['id_entrega_pedido'], ['id_entrega', 'id_venta', 'estado_pedido_entrega', 'motivo_rechazo']);
        }

        // 1.4 Remitos emitidos
        if (Schema::hasTable('remito')) {
            DB::table('remito')->upsert([
                [
                    'id_remito' => 1,
                    'numero_remito' => 'REM-0001-00000101',
                    'id_entrega' => 2,
                    'id_venta' => 101,
                    'fecha_emision' => now()->subDay(),
                    'estado' => 'entregado_conforme',
                    'observaciones' => 'Entregado conforme en Supermercado Central.',
                ],
                [
                    'id_remito' => 2,
                    'numero_remito' => 'REM-0001-00000103',
                    'id_entrega' => 1,
                    'id_venta' => 103,
                    'fecha_emision' => now(),
                    'estado' => 'emitido',
                    'observaciones' => 'Comprobante de viaje en curso para Despensa San José.',
                ],
            ], ['id_remito'], ['numero_remito', 'id_entrega', 'id_venta', 'estado', 'observaciones']);
        }

        if (Schema::hasTable('detalle_remito')) {
            DB::table('detalle_remito')->upsert([
                ['id_detalle_remito' => 1, 'id_remito' => 1, 'id_producto' => 1, 'descripcion' => 'Aceite Girasol 1.5L', 'cantidad' => 2, 'bultos' => 2],
                ['id_detalle_remito' => 2, 'id_remito' => 1, 'id_producto' => 2, 'descripcion' => 'Harina 000 1kg', 'cantidad' => 1, 'bultos' => 1],
            ], ['id_detalle_remito'], ['id_remito', 'id_producto', 'descripcion', 'cantidad', 'bultos']);
        }

        // ================================================================
        // 2. MÓDULO CAJA (G2)
        // ================================================================
        if (Schema::hasTable('CAJA')) {
            // Se siembra una caja abierta hoy para el Repartidor (Matías #1 - usuario provisorio de CajaWebController)
            // y para el Admin (Diego #2) para permitir recibir cobros en efectivo inmediatamente en la demo
            DB::table('CAJA')->upsert([
                [
                    'id_caja' => 1,
                    'id_usuario' => 1, // Matías Repartidor
                    'fecha' => now()->toDateString(),
                    'monto_inicial' => 50000.00,
                    'monto_final' => null,
                    'monto_esperado' => null,
                    'diferencia' => null,
                    'estado' => 'abierta',
                    'id_usuario_cierre' => null,
                    'fecha_hora_apertura' => now()->startOfDay()->addHours(8),
                    'fecha_hora_cierre' => null,
                    'fecha_vencimiento' => now()->toDateString(),
                    'observaciones' => 'Caja operativa diaria abierta para recepción de cobranzas de repartos.',
                ],
                [
                    'id_caja' => 2,
                    'id_usuario' => 2, // Diego Admin
                    'fecha' => now()->toDateString(),
                    'monto_inicial' => 80000.00,
                    'monto_final' => null,
                    'monto_esperado' => null,
                    'diferencia' => null,
                    'estado' => 'abierta',
                    'id_usuario_cierre' => null,
                    'fecha_hora_apertura' => now()->startOfDay()->addHours(8),
                    'fecha_hora_cierre' => null,
                    'fecha_vencimiento' => now()->toDateString(),
                    'observaciones' => 'Caja de administración central.',
                ],
            ], ['id_caja'], ['id_usuario', 'fecha', 'monto_inicial', 'estado', 'observaciones']);
        }

        // ================================================================
        // 3. MÓDULO RENDICIONES (G2)
        // ================================================================
        if (Schema::hasTable('RENDICION')) {
            DB::table('RENDICION')->upsert([
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
                    'observaciones' => 'Transferencia bancaria — Autoservicio El Amigo (#102).',
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
                    'observaciones' => 'Cobro en Efectivo — Despensa San José (#103). Lista para validar y pasar a Caja.',
                    'id_usuario_validador' => null,
                    'fecha_validacion' => null,
                    'motivo_rechazo' => null,
                ],
                [
                    'id_rendicion' => 4,
                    'id_repartidor' => 4,
                    'id_entrega' => 1,
                    'fecha' => now(),
                    'total_rendido' => 50000.00,
                    'estado' => 'rechazada',
                    'observaciones' => 'Transferencia declarada sin respaldo.',
                    'id_usuario_validador' => 3,
                    'fecha_validacion' => now()->subHours(1),
                    'motivo_rechazo' => 'No se encontró el comprobante de transferencia en el homebanking.',
                ]
            ], ['id_rendicion'], ['id_repartidor', 'id_entrega', 'fecha', 'total_rendido', 'estado', 'observaciones', 'id_usuario_validador', 'fecha_validacion', 'motivo_rechazo']);
        }

        if (Schema::hasTable('RENDICION_COBRO')) {
            DB::table('RENDICION_COBRO')->upsert([
                ['id_rendicion_cobro' => 1, 'id_rendicion' => 1, 'id_cliente' => 1, 'id_factura' => 101, 'monto' => 45500.00, 'medio_pago' => 'efectivo', 'fecha_registro' => now()->subDay()],
                ['id_rendicion_cobro' => 2, 'id_rendicion' => 2, 'id_cliente' => 2, 'id_factura' => 102, 'monto' => 28000.00, 'medio_pago' => 'transferencia', 'fecha_registro' => now()->subDay()],
                ['id_rendicion_cobro' => 3, 'id_rendicion' => 3, 'id_cliente' => 3, 'id_factura' => 103, 'monto' => 15200.00, 'medio_pago' => 'efectivo', 'fecha_registro' => now()],
                ['id_rendicion_cobro' => 4, 'id_rendicion' => 4, 'id_cliente' => 2, 'id_factura' => 102, 'monto' => 50000.00, 'medio_pago' => 'transferencia', 'fecha_registro' => now()],
            ], ['id_rendicion_cobro'], ['id_rendicion', 'id_cliente', 'id_factura', 'monto', 'medio_pago', 'fecha_registro']);
        }

        // ================================================================
        // 4. MÓDULOS EXTERNOS (Ventas G4, Clientes, Catálogo)
        // NOTA: Se cargan de forma condicional ÚNICAMENTE como fallback
        // local para cuando el sistema se ejecute desconectado de la API G4.
        // ================================================================
        if (Schema::hasTable('CLIENTE')) {
            DB::table('CLIENTE')->upsert([
                ['id_cliente' => 1, 'nombre' => 'Supermercado Central (Pigüé)', 'razon_social' => 'Super Central S.R.L.', 'cuit' => '30-11223344-5', 'direccion' => 'Av. Casey 450', 'localidad' => 'Pigüé', 'telefono' => '02923-471122', 'id_zona' => 1, 'estado' => 'activo'],
                ['id_cliente' => 2, 'nombre' => 'Autoservicio El Amigo', 'razon_social' => 'El Amigo S.A.', 'cuit' => '30-22334455-6', 'direccion' => 'San Martín 120', 'localidad' => 'Saavedra', 'telefono' => '02923-451122', 'id_zona' => 1, 'estado' => 'activo'],
                ['id_cliente' => 3, 'nombre' => 'Despensa San José', 'razon_social' => 'San José Distribución', 'cuit' => '30-33445566-7', 'direccion' => 'Belgrano 890', 'localidad' => 'Tornquist', 'telefono' => '0291-4940112', 'id_zona' => 2, 'estado' => 'activo'],
            ], ['id_cliente'], ['nombre', 'razon_social', 'cuit', 'direccion', 'localidad', 'telefono', 'id_zona', 'estado']);
        }

        if (Schema::hasTable('producto')) {
            DB::table('producto')->upsert([
                ['id_producto' => 1, 'codigo' => 'ACE-GIR-01', 'nombre' => 'Aceite Girasol 1.5L (Caja x 12)', 'descripcion' => 'Caja con 12 botellas de 1.5L', 'precio_unitario' => 18500.00, 'bultos_por_unidad' => 1, 'stock' => 150, 'estado' => 'activo'],
                ['id_producto' => 2, 'codigo' => 'HAR-000-01', 'nombre' => 'Harina 000 1kg (Fardo x 10)', 'descripcion' => 'Fardo de 10 paquetes de 1kg', 'precio_unitario' => 9800.00, 'bultos_por_unidad' => 1, 'stock' => 300, 'estado' => 'activo'],
                ['id_producto' => 3, 'codigo' => 'AZU-LED-01', 'nombre' => 'Azúcar Ledesma 1kg (Fardo x 10)', 'descripcion' => 'Fardo de 10 paquetes de 1kg', 'precio_unitario' => 12500.00, 'bultos_por_unidad' => 1, 'stock' => 200, 'estado' => 'activo'],
            ], ['id_producto'], ['codigo', 'nombre', 'descripcion', 'precio_unitario', 'bultos_por_unidad', 'stock', 'estado']);
        }

        if (Schema::hasTable('VENTA')) {
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
                    'estado' => 'entregada',
                    'observaciones' => 'Pedido entregado y rendido.',
                    'pagado' => true
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
                    'estado' => 'entregada',
                    'observaciones' => 'Pedido entregado y rendido vía transferencia.',
                    'pagado' => true
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
                    'estado' => 'en_camino',
                    'observaciones' => 'Pedido en reparto (Entrega #1), pendiente de rendición.',
                    'pagado' => false
                ],
                [
                    'id_venta' => 104,
                    'id_cliente' => 1,
                    'id_usuario' => 1,
                    'fecha' => now(),
                    'monto_total' => 32000.00,
                    'total' => 32000.00,
                    'total_bultos' => 5,
                    'total_sueltos' => 0,
                    'estado' => 'confirmada',
                    'observaciones' => 'Venta confirmada lista para armar una nueva Entrega en vivo.',
                    'pagado' => false
                ],
            ], ['id_venta'], ['id_cliente', 'id_usuario', 'fecha', 'monto_total', 'total', 'total_bultos', 'total_sueltos', 'estado', 'observaciones', 'pagado']);
        }

        if (Schema::hasTable('detalle_venta')) {
            DB::table('detalle_venta')->upsert([
                ['id_detalle_venta' => 1, 'id_venta' => 101, 'id_producto' => 1, 'cantidad' => 2, 'precio_unitario' => 18500.00, 'subtotal' => 37000.00, 'bultos' => 2, 'unidades_sueltas' => 0],
                ['id_detalle_venta' => 2, 'id_venta' => 101, 'id_producto' => 2, 'cantidad' => 1, 'precio_unitario' => 8500.00, 'subtotal' => 8500.00, 'bultos' => 1, 'unidades_sueltas' => 2],
                ['id_detalle_venta' => 3, 'id_venta' => 104, 'id_producto' => 3, 'cantidad' => 2, 'precio_unitario' => 12500.00, 'subtotal' => 25000.00, 'bultos' => 2, 'unidades_sueltas' => 0],
            ], ['id_detalle_venta'], ['id_venta', 'id_producto', 'cantidad', 'precio_unitario', 'subtotal', 'bultos', 'unidades_sueltas']);
        }
    }
}
