<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Entregas\Services\EntregaService;
use App\Entregas\Repositories\EntregaRepository;
use App\Entregas\Repositories\RemitoRepository;
use Exception;
use Mockery;

class EntregaServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rechaza_confirmar_pedido_con_id_invalido(): void
    {
        $entregaRepo = Mockery::mock(EntregaRepository::class);
        $remitoRepo = Mockery::mock(RemitoRepository::class);
        $service = new EntregaService($entregaRepo, $remitoRepo);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El ID de entrega es inválido.");

        $service->confirmarPedidoEntregado(0, 101);
    }

    public function test_rechaza_confirmar_pedido_si_entrega_no_existe(): void
    {
        $entregaRepo = Mockery::mock(EntregaRepository::class);
        $remitoRepo = Mockery::mock(RemitoRepository::class);

        $entregaRepo->shouldReceive('obtenerEntregaPorId')
            ->once()
            ->with(999)
            ->andReturn(null);

        $service = new EntregaService($entregaRepo, $remitoRepo);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("La entrega #999 no existe.");

        $service->confirmarPedidoEntregado(999, 101);
    }

    public function test_rechaza_confirmar_pedido_si_no_pertenece_a_la_entrega(): void
    {
        $entregaRepo = Mockery::mock(EntregaRepository::class);
        $remitoRepo = Mockery::mock(RemitoRepository::class);

        $entregaRepo->shouldReceive('obtenerEntregaPorId')
            ->once()
            ->with(10)
            ->andReturn([
                'id_entrega' => 10,
                'pedidos' => [
                    ['id_venta' => 105, 'estado_pedido_entrega' => 'en_preparacion']
                ]
            ]);

        $service = new EntregaService($entregaRepo, $remitoRepo);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El pedido #101 no está asociado a la entrega #10.");

        $service->confirmarPedidoEntregado(10, 101);
    }

    public function test_rechaza_confirmar_pedido_si_ya_estaba_entregado(): void
    {
        $entregaRepo = Mockery::mock(EntregaRepository::class);
        $remitoRepo = Mockery::mock(RemitoRepository::class);

        $entregaRepo->shouldReceive('obtenerEntregaPorId')
            ->once()
            ->with(10)
            ->andReturn([
                'id_entrega' => 10,
                'pedidos' => [
                    ['id_venta' => 101, 'estado_pedido_entrega' => 'entregado', 'cliente_nombre' => 'Supermercado Central']
                ]
            ]);

        $service = new EntregaService($entregaRepo, $remitoRepo);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El pedido #101 ya se encuentra confirmado como entregado.");

        $service->confirmarPedidoEntregado(10, 101);
    }

    public function test_confirma_pedido_entregado_exitosamente(): void
    {
        $entregaRepo = Mockery::mock(EntregaRepository::class);
        $remitoRepo = Mockery::mock(RemitoRepository::class);

        $entregaRepo->shouldReceive('obtenerEntregaPorId')
            ->once()
            ->with(10)
            ->andReturn([
                'id_entrega' => 10,
                'pedidos' => [
                    ['id_venta' => 101, 'estado_pedido_entrega' => 'en_camino', 'cliente_nombre' => 'Supermercado Central']
                ]
            ]);

        $entregaRepo->shouldReceive('marcarPedidoComoEntregado')
            ->once()
            ->with(10, 101, 'Entregado conforme');

        $service = new EntregaService($entregaRepo, $remitoRepo);

        $resultado = $service->confirmarPedidoEntregado(10, 101, 'Entregado conforme');

        $this->assertEquals(10, $resultado['id_entrega']);
        $this->assertEquals(101, $resultado['id_venta']);
        $this->assertEquals('entregado', $resultado['estado_pedido']);
        $this->assertStringContainsString('ENTREGADO exitosamente', $resultado['mensaje']);
    }
}
