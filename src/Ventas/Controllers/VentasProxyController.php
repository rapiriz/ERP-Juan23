<?php

namespace App\Ventas\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\Response;
use App\Ventas\Services\VentasApiClient;
use Illuminate\Http\Request;

class VentasProxyController extends Controller
{
    private VentasApiClient $ventasClient;

    public function __construct(?VentasApiClient $ventasClient = null)
    {
        $this->ventasClient = $ventasClient ?? new VentasApiClient();
    }

    /**
     * GET /api/v1/ventas/remotas
     * Consulta las ventas pendientes de despacho mediante el cliente HTTP de la API del Grupo 4.
     */
    public function obtenerVentasRemotas(Request $request): void
    {
        try {
            $idZona = $request->query('id_zona') ? (int)$request->query('id_zona') : null;
            $ventas = $this->ventasClient->obtenerVentasPendientesDespacho($idZona);

            Response::json([
                'ventas' => $ventas,
                'cantidad' => count($ventas),
                'api_url_configurada' => $this->ventasClient->getBaseUrl(),
                'modo' => !empty($ventas[0]['origen']) && $ventas[0]['origen'] === 'api_grupo_4' ? 'remoto_g4' : 'fallback_local'
            ], 200, "Ventas obtenidas exitosamente mediante API de Grupo 4.");
        } catch (\Throwable $e) {
            Response::error("Error al obtener ventas de la API remota: " . $e->getMessage(), 500);
        }
    }

    /**
     * Endpoint Mock contractual que simula el microservicio de Grupo 4.
     * GET /api/v1/mock/grupo4/ventas
     */
    public function mockGrupo4Ventas(Request $request): void
    {
        $idZona = $request->query('id_zona') ? (int)$request->query('id_zona') : null;
        $mockVentas = self::obtenerCatalogoMockVentas();

        if ($idZona !== null && $idZona > 0) {
            $mockVentas = array_values(array_filter($mockVentas, fn($v) => (int)$v['id_zona'] === $idZona));
        }

        Response::json([
            'status' => 'success',
            'data' => $mockVentas
        ], 200, "Ventas confirmadas de Grupo 4 (Simulación contractual)");
    }

    /**
     * GET /api/v1/mock/grupo4/ventas/{id}
     */
    public function mockGrupo4VentaDetalle(int $id): void
    {
        $ventas = self::obtenerCatalogoMockVentas();
        foreach ($ventas as $v) {
            if ((int)$v['id_venta'] === (int)$id) {
                Response::json([
                    'status' => 'success',
                    'data' => $v
                ], 200, "Detalle de Venta #{$id} provisto por API Ventas (G4)");
                return;
            }
        }

        // Si no está en el catálogo fijo, generar mock dinámico
        Response::json([
            'status' => 'success',
            'data' => [
                'id_venta' => (int)$id,
                'fecha' => date('Y-m-d H:i:s'),
                'total' => 25000.00,
                'monto_total' => 25000.00,
                'total_bultos' => 2,
                'total_sueltos' => 0,
                'estado' => 'confirmada',
                'id_cliente' => 1,
                'cliente_nombre' => "Cliente Pedido #{$id}",
                'razon_social' => "Razón Social #{$id}",
                'direccion' => "Calle Comercial {$id}",
                'localidad' => "Pigüé",
                'telefono' => "02923-400000",
                'id_zona' => 1,
                'zona_nombre' => "Ruta Saavedra",
                'items' => [
                    [
                        'id_producto' => 1,
                        'codigo' => 'ART-' . $id,
                        'producto_nombre' => "Mercadería Pedido #{$id}",
                        'cantidad' => 2,
                        'precio_unitario' => 12500.00,
                        'subtotal' => 25000.00,
                        'bultos' => 2
                    ]
                ]
            ]
        ], 200);
    }

    /**
     * PATCH /api/v1/mock/grupo4/ventas/{id}/estado
     */
    public function mockGrupo4ActualizarEstado(Request $request, int $id): void
    {
        $nuevoEstado = $request->input('estado') ?? 'en_camino';
        Response::json([
            'status' => 'success',
            'mensaje' => "Estado de la Venta #{$id} actualizado a '{$nuevoEstado}' en API Ventas (G4).",
            'id_venta' => (int)$id,
            'nuevo_estado' => $nuevoEstado
        ], 200);
    }

    public static function obtenerCatalogoMockVentas(): array
    {
        return [
            [
                'id_venta' => 101,
                'fecha' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'total' => 45500.00,
                'monto_total' => 45500.00,
                'total_bultos' => 10,
                'total_sueltos' => 2,
                'estado' => 'confirmada',
                'observaciones' => 'Entrega en horario comercial. Cobro en efectivo.',
                'id_cliente' => 1,
                'cliente_nombre' => 'Supermercado Central (Pigüé)',
                'razon_social' => 'Super Central S.R.L.',
                'direccion' => 'Av. Casey 450',
                'localidad' => 'Pigüé',
                'telefono' => '02923-471122',
                'id_zona' => 1,
                'zona_nombre' => 'Ruta Saavedra',
                'items' => [
                    [
                        'id_producto' => 1,
                        'codigo' => 'ACE-GIR-01',
                        'producto_nombre' => 'Aceite Girasol 1.5L (Caja x 12)',
                        'cantidad' => 2,
                        'precio_unitario' => 18500.00,
                        'subtotal' => 37000.00,
                        'bultos' => 2
                    ],
                    [
                        'id_producto' => 2,
                        'codigo' => 'HAR-000-01',
                        'producto_nombre' => 'Harina 000 1kg (Fardo x 10)',
                        'cantidad' => 1,
                        'precio_unitario' => 8500.00,
                        'subtotal' => 8500.00,
                        'bultos' => 1
                    ]
                ]
            ],
            [
                'id_venta' => 102,
                'fecha' => date('Y-m-d H:i:s'),
                'total' => 28000.00,
                'monto_total' => 28000.00,
                'total_bultos' => 6,
                'total_sueltos' => 0,
                'estado' => 'confirmada',
                'observaciones' => 'Descarga por portón trasero.',
                'id_cliente' => 2,
                'cliente_nombre' => 'Autoservicio El Amigo',
                'razon_social' => 'El Amigo S.A.',
                'direccion' => 'San Martín 120',
                'localidad' => 'Saavedra',
                'telefono' => '02923-451122',
                'id_zona' => 1,
                'zona_nombre' => 'Ruta Saavedra',
                'items' => [
                    [
                        'id_producto' => 2,
                        'codigo' => 'HAR-000-01',
                        'producto_nombre' => 'Harina 000 1kg (Fardo x 10)',
                        'cantidad' => 2,
                        'precio_unitario' => 14000.00,
                        'subtotal' => 28000.00,
                        'bultos' => 2
                    ]
                ]
            ],
            [
                'id_venta' => 103,
                'fecha' => date('Y-m-d H:i:s'),
                'total' => 15200.00,
                'monto_total' => 15200.00,
                'total_bultos' => 3,
                'total_sueltos' => 1,
                'estado' => 'en_camino',
                'observaciones' => 'Cobro en Efectivo — Despensa San José.',
                'id_cliente' => 3,
                'cliente_nombre' => 'Despensa San José',
                'razon_social' => 'San José Distribución',
                'direccion' => 'Belgrano 890',
                'localidad' => 'Tornquist',
                'telefono' => '0291-4940112',
                'id_zona' => 2,
                'zona_nombre' => 'Ruta Tornquist',
                'items' => [
                    [
                        'id_producto' => 3,
                        'codigo' => 'AZU-LED-01',
                        'producto_nombre' => 'Azúcar Ledesma 1kg (Fardo x 10)',
                        'cantidad' => 1,
                        'precio_unitario' => 15200.00,
                        'subtotal' => 15200.00,
                        'bultos' => 1
                    ]
                ]
            ],
            [
                'id_venta' => 104,
                'fecha' => date('Y-m-d H:i:s'),
                'total' => 32000.00,
                'monto_total' => 32000.00,
                'total_bultos' => 5,
                'total_sueltos' => 0,
                'estado' => 'confirmada',
                'observaciones' => 'Venta lista para planificar nueva entrega.',
                'id_cliente' => 1,
                'cliente_nombre' => 'Supermercado Central (Pigüé)',
                'razon_social' => 'Super Central S.R.L.',
                'direccion' => 'Av. Casey 450',
                'localidad' => 'Pigüé',
                'telefono' => '02923-471122',
                'id_zona' => 1,
                'zona_nombre' => 'Ruta Saavedra',
                'items' => [
                    [
                        'id_producto' => 1,
                        'codigo' => 'ACE-GIR-01',
                        'producto_nombre' => 'Aceite Girasol 1.5L (Caja x 12)',
                        'cantidad' => 1,
                        'precio_unitario' => 18500.00,
                        'subtotal' => 18500.00,
                        'bultos' => 1
                    ],
                    [
                        'id_producto' => 3,
                        'codigo' => 'AZU-LED-01',
                        'producto_nombre' => 'Azúcar Ledesma 1kg (Fardo x 10)',
                        'cantidad' => 1,
                        'precio_unitario' => 13500.00,
                        'subtotal' => 13500.00,
                        'bultos' => 1
                    ]
                ]
            ]
        ];
    }
}
