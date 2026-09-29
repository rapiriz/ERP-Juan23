<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Rendiciones\Services\RendicionService;
use App\Rendiciones\Repositories\RendicionRepository;
use App\Rendiciones\Models\Rendicion;
use InvalidArgumentException;
use Mockery;

class RendicionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rechaza_rendicion_si_repartidor_no_esta_activo(): void
    {
        $repoMock = Mockery::mock(RendicionRepository::class);
        $repoMock->shouldReceive('repartidorEstaActivo')
            ->once()
            ->with(999)
            ->andReturn(false);

        $service = new RendicionService($repoMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("El repartidor con id 999 no existe o no se encuentra activo.");

        $service->registrarRendicion([
            'id_repartidor' => 999,
            'cobros' => [['id_cliente' => 1, 'id_factura' => 10, 'monto' => 500, 'medio_pago' => 'efectivo']],
        ]);
    }

    public function test_rechaza_rendicion_sin_cobros_ni_remitos(): void
    {
        $repoMock = Mockery::mock(RendicionRepository::class);
        $repoMock->shouldReceive('repartidorEstaActivo')
            ->once()
            ->with(1)
            ->andReturn(true);

        $service = new RendicionService($repoMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("La rendición debe incluir al menos un cobro o un remito entregado.");

        $service->registrarRendicion([
            'id_repartidor' => 1,
            'cobros' => [],
            'remitos' => [],
        ]);
    }

    public function test_rechaza_rendicion_con_monto_invalido_en_cobro(): void
    {
        $repoMock = Mockery::mock(RendicionRepository::class);
        $repoMock->shouldReceive('repartidorEstaActivo')
            ->once()
            ->with(1)
            ->andReturn(true);

        $service = new RendicionService($repoMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Se detectó un monto inválido dentro del listado de cobros de la rendición.");

        $service->registrarRendicion([
            'id_repartidor' => 1,
            'cobros' => [['id_cliente' => 1, 'id_factura' => 10, 'monto' => -50, 'medio_pago' => 'efectivo']],
        ]);
    }

    public function test_rechaza_revision_con_accion_invalida(): void
    {
        $rendicion = new Rendicion();
        $rendicion->id_rendicion = 5;

        $repoMock = Mockery::mock(RendicionRepository::class);
        $repoMock->shouldReceive('buscarPorId')
            ->once()
            ->with(5)
            ->andReturn($rendicion);

        $service = new RendicionService($repoMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("La acción 'eliminar' no es válida. Opciones permitidas: aprobar, rechazar.");

        $service->revisarRendicion(5, [
            'accion' => 'eliminar',
            'id_usuario_validador' => 2,
        ]);
    }
}
