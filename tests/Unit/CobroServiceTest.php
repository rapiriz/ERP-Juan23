<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Cobros\Services\CobroService;
use App\Cobros\Repositories\CobroRepository;
use App\Cobros\Models\Cobro;
use InvalidArgumentException;
use Mockery;

class CobroServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rechaza_cobro_con_monto_cero_o_negativo(): void
    {
        $repositoryMock = Mockery::mock(CobroRepository::class);
        $service = new CobroService($repositoryMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("El monto del cobro debe ser mayor a cero.");

        $service->registrarCobro([
            'id_cliente' => 1,
            'monto_total' => 0,
        ]);
    }

    public function test_rechaza_cobro_si_cliente_no_esta_activo(): void
    {
        $repositoryMock = Mockery::mock(CobroRepository::class);
        $repositoryMock->shouldReceive('clienteEstaActivo')
            ->once()
            ->with(999)
            ->andReturn(false);

        $service = new CobroService($repositoryMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("El cliente no existe o no se encuentra activo.");

        $service->registrarCobro([
            'id_cliente' => 999,
            'monto_total' => 1000,
        ]);
    }

    public function test_rechaza_medio_de_pago_invalido(): void
    {
        $repositoryMock = Mockery::mock(CobroRepository::class);
        $repositoryMock->shouldReceive('clienteEstaActivo')
            ->once()
            ->with(1)
            ->andReturn(true);

        $service = new CobroService($repositoryMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Medio de pago inválido.");

        $service->registrarCobro([
            'id_cliente' => 1,
            'monto_total' => 1000,
            'medio_pago' => 'criptomoneda',
        ]);
    }
}
