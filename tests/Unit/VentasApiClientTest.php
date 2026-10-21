<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Modules\Ventas\Services\VentasApiClient;
use Illuminate\Support\Facades\Http;

class VentasApiClientTest extends TestCase
{
    public function test_obtiene_ventas_desde_api_grupo_4_satisfactoriamente(): void
    {
        Http::fake([
            'http://test-ventas.local/api/v1/ventas*' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'id_venta' => 101,
                        'total' => 45500.00,
                        'bultos' => 10,
                        'observaciones' => 'Entrega matutina',
                        'cliente' => [
                            'id_cliente' => 1,
                            'nombre' => 'Supermercado Central',
                            'direccion' => 'Av. Casey 450'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $client = new VentasApiClient('http://test-ventas.local/api/v1', 2);
        $ventas = $client->obtenerVentasPendientesDespacho();

        $this->assertCount(1, $ventas);
        $this->assertEquals(101, $ventas[0]['id_venta']);
        $this->assertEquals(45500.00, $ventas[0]['total']);
        $this->assertEquals('Supermercado Central', $ventas[0]['cliente_nombre']);
        $this->assertEquals('api_grupo_4', $ventas[0]['origen']);
    }

    public function test_actualiza_estado_de_venta_en_api_grupo_4(): void
    {
        Http::fake([
            'http://test-ventas.local/api/v1/ventas/101/estado' => Http::response([
                'status' => 'success',
                'message' => 'Estado actualizado'
            ], 200)
        ]);

        $client = new VentasApiClient('http://test-ventas.local/api/v1', 2);
        $resultado = $client->actualizarEstadoVenta(101, 'en_camino');

        $this->assertTrue($resultado);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://test-ventas.local/api/v1/ventas/101/estado' &&
                   $request['estado'] === 'en_camino';
        });
    }

    public function test_fallback_local_cuando_api_grupo_4_falla(): void
    {
        Http::fake([
            'http://test-ventas.local/api/v1/*' => Http::response('Error', 500)
        ]);

        $client = new VentasApiClient('http://test-ventas.local/api/v1', 1);
        $ventas = $client->obtenerVentasPendientesDespacho();

        // En caso de fallo de red, debe retornar un array sin lanzar excepción
        $this->assertIsArray($ventas);
    }
}
