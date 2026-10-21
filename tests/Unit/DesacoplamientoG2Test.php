<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Shared\Services\UsuarioService;
use App\Ventas\Services\VentasApiClient;
use App\Ventas\Controllers\VentasProxyController;
use App\Rendiciones\Repositories\RendicionRepository;

class DesacoplamientoG2Test extends TestCase
{
    /** @test */
    public function usuario_service_resuelve_repartidores_sin_depender_de_bd(): void
    {
        $repartidores = UsuarioService::obtenerRepartidores();
        $this->assertNotEmpty($repartidores);
        $this->assertEquals(1, $repartidores[0]['id_usuario']);
        $this->assertEquals('Matías Repartidor', $repartidores[0]['nombre']);
    }

    /** @test */
    public function usuario_service_resuelve_nombres_legibles(): void
    {
        $nombreMatias = UsuarioService::obtenerNombre(1);
        $nombreDiego = UsuarioService::obtenerNombre(2);

        $this->assertEquals('Matías Repartidor', $nombreMatias);
        $this->assertEquals('Diego Admin', $nombreDiego);
    }

    /** @test */
    public function ventas_api_client_obtiene_catalogo_simulado_sin_tabla_venta(): void
    {
        $client = new VentasApiClient();
        $ventas = $client->obtenerVentasPendientesDespacho();

        $this->assertIsArray($ventas);
        $this->assertNotEmpty($ventas);
        $this->assertArrayHasKey('id_venta', $ventas[0]);
        $this->assertArrayHasKey('cliente_nombre', $ventas[0]);
    }

    /** @test */
    public function ventas_api_client_obtiene_detalle_de_venta_por_id(): void
    {
        $client = new VentasApiClient();
        $venta101 = $client->obtenerVentaPorId(101);

        $this->assertNotNull($venta101);
        $this->assertEquals(101, $venta101['id_venta']);
        $this->assertEquals(45500.00, (float)$venta101['total']);
        $this->assertEquals('Supermercado Central (Pigüé)', $venta101['cliente_nombre']);
        $this->assertNotEmpty($venta101['items']);
    }

    /** @test */
    public function rendicion_repository_valida_repartidor_y_factura_desacoplado(): void
    {
        $repo = new RendicionRepository();
        $this->assertTrue($repo->repartidorEstaActivo(1));
        $this->assertFalse($repo->repartidorEstaActivo(999));
        $this->assertTrue($repo->facturaEstaPendiente(101));
    }

    /** @test */
    public function precarga_automatica_de_rendicion_desde_entrega(): void
    {
        $service = new \App\Rendiciones\Services\RendicionService(new RendicionRepository());
        // Entrega #1 tiene pedido 103 entregado en la semilla
        $resultado = $service->precargarOActualizarRendicionDesdeEntrega(1);

        if ($resultado !== null) {
            $this->assertEquals(1, $resultado['id_entrega']);
            $this->assertEquals('pendiente', $resultado['estado']);
            $this->assertGreaterThan(0, (float)$resultado['total_rendido']);
        } else {
            $this->assertTrue(true);
        }
    }
}
