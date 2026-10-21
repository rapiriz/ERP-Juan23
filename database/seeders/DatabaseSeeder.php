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
     * Enfoque estricto: EXCLUSIVAMENTE entidades propias del Grupo 2
     * (Logística/Entregas, Rendiciones, Caja y Conciliación Bancaria).
     * Los módulos externos (Usuarios, Clientes, Ventas, Catálogo) se resuelven
     * de forma desacoplada mediante mocks y clientes de API.
     */
    public function run(): void
    {
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

        // 1.3 Entrega - Pedidos (Asociación con IDs lógicos de ventas de G4)
        if (Schema::hasTable('entrega_pedido')) {
            DB::table('entrega_pedido')->upsert([
                [
                    'id_entrega_pedido' => 1,
                    'id_entrega' => 1,
                    'id_venta' => 103, // Mock Pedido Despensa San José ($15.200)
                    'estado_pedido_entrega' => 'entregado',
                    'motivo_rechazo' => null,
                    'fecha_actualizacion' => now(),
                ],
                [
                    'id_entrega_pedido' => 2,
                    'id_entrega' => 2,
                    'id_venta' => 101, // Mock Pedido Supermercado Central ($45.500)
                    'estado_pedido_entrega' => 'entregado',
                    'motivo_rechazo' => null,
                    'fecha_actualizacion' => now()->subDay(),
                ],
                [
                    'id_entrega_pedido' => 3,
                    'id_entrega' => 2,
                    'id_venta' => 102, // Mock Pedido Autoservicio El Amigo ($28.000)
                    'estado_pedido_entrega' => 'entregado',
                    'motivo_rechazo' => null,
                    'fecha_actualizacion' => now()->subDay(),
                ],
            ], ['id_entrega_pedido'], ['id_entrega', 'id_venta', 'estado_pedido_entrega', 'motivo_rechazo']);
        }

        
        // ================================================================
        // 2. MÓDULO CAJA (G2)
        // ================================================================
        if (Schema::hasTable('CAJA')) {
            // Caja operativa abierta para recibir cobros de rendición en efectivo
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
                    'cierre_automatico' => false,
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

        // 3.2 Desglose de cobranzas por pedido
        if (Schema::hasTable('RENDICION_COBRO')) {
            DB::table('RENDICION_COBRO')->upsert([
                ['id_rendicion_cobro' => 1, 'id_rendicion' => 1, 'id_cliente' => 1, 'id_factura' => 101, 'monto' => 45500.00, 'medio_pago' => 'efectivo', 'fecha_registro' => now()->subDay()],
                ['id_rendicion_cobro' => 2, 'id_rendicion' => 2, 'id_cliente' => 2, 'id_factura' => 102, 'monto' => 28000.00, 'medio_pago' => 'transferencia', 'fecha_registro' => now()->subDay()],
                ['id_rendicion_cobro' => 3, 'id_rendicion' => 3, 'id_cliente' => 3, 'id_factura' => 103, 'monto' => 15200.00, 'medio_pago' => 'efectivo', 'fecha_registro' => now()],
                ['id_rendicion_cobro' => 4, 'id_rendicion' => 4, 'id_cliente' => 2, 'id_factura' => 102, 'monto' => 50000.00, 'medio_pago' => 'transferencia', 'fecha_registro' => now()],
            ], ['id_rendicion_cobro'], ['id_rendicion', 'id_cliente', 'id_factura', 'monto', 'medio_pago', 'fecha_registro']);
        }

        // ================================================================
        // 4. MÓDULO CONCILIACIÓN BANCARIA (G2)
        // ================================================================
        if (Schema::hasTable('PERIODO_CONCILIACION')) {
            DB::table('PERIODO_CONCILIACION')->upsert([
                [
                    'id_periodo' => 1,
                    'fecha_desde' => now()->startOfMonth()->toDateString(),
                    'fecha_hasta' => now()->endOfMonth()->toDateString(),
                    'estado' => 'abierto',
                    'fecha_apertura' => now()->startOfMonth()->startOfDay(),
                    'fecha_cierre' => null,
                    'id_usuario' => 2,
                ]
            ], ['id_periodo'], ['fecha_desde', 'fecha_hasta', 'estado', 'fecha_apertura']);
        }
    }
}
