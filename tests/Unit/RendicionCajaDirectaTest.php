<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Rendiciones\Services\RendicionService;
use App\Rendiciones\Repositories\RendicionRepository;
use App\Rendiciones\Models\Rendicion;
use App\Rendiciones\Models\RendicionCobro;
use App\Caja\Services\CajaService;
use App\Caja\Repositories\CajaRepository;
use App\Caja\Models\Caja;
use App\Caja\Models\CajaMovimiento;
use Mockery;

class RendicionCajaDirectaTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_aprobacion_de_rendicion_cambia_estado_correctamente(): void
    {
        $rendicion = new Rendicion();
        $rendicion->id_rendicion = 1;
        $rendicion->estado = 'pendiente';
        $rendicion->id_repartidor = 1;

        $cobro = new RendicionCobro();
        $cobro->id_rendicion_cobro = 1;
        $cobro->id_rendicion = 1;
        $cobro->id_cliente = 1;
        $cobro->id_factura = 101;
        $cobro->monto = 45500.00;
        $cobro->medio_pago = 'efectivo';

        $rendicion->setRelation('cobros', collect([$cobro]));

        $repoMock = Mockery::mock(RendicionRepository::class);
        $repoMock->shouldReceive('buscarPorId')
            ->once()
            ->with(1)
            ->andReturn($rendicion);

        $repoMock->shouldReceive('guardar')
            ->once()
            ->with($rendicion)
            ->andReturn($rendicion);

        $service = new RendicionService($repoMock);

        $resultado = $service->revisarRendicion(1, [
            'accion' => 'aprobar',
            'id_usuario_validador' => 2
        ]);

        $this->assertEquals('aprobada', $resultado['estado_actual']);
        $this->assertEquals(2, $resultado['id_usuario_validador']);
        $this->assertNotNull($resultado['fecha_validacion']);
    }

    public function test_caja_service_registra_cobro_en_efectivo_en_caja_abierta(): void
    {
        $caja = new Caja();
        $caja->id_caja = 10;
        $caja->id_usuario = 2;
        $caja->monto_inicial = 10000.00;
        $caja->estado = Caja::ESTADO_ABIERTA;

        $movimiento = new CajaMovimiento();
        $movimiento->id_movimiento_caja = 50;
        $movimiento->id_caja = 10;
        $movimiento->monto = 45500.00;
        $movimiento->tipo = CajaMovimiento::TIPO_COBRO;
        $movimiento->concepto = "Cobro Venta #101 en Efectivo";

        $cajaRepoMock = Mockery::mock(CajaRepository::class);
        $cajaRepoMock->shouldReceive('buscarCualquierAbiertaDeUsuario')
            ->once()
            ->with(2)
            ->andReturn($caja);

        $cajaRepoMock->shouldReceive('buscarPorId')
            ->once()
            ->with(10)
            ->andReturn($caja);

        $cajaRepoMock->shouldReceive('crearMovimiento')
            ->once()
            ->andReturn($movimiento);

        $cajaService = new CajaService($cajaRepoMock);

        $res = $cajaService->registrarCobroEnEfectivo(
            2,
            Caja::ROL_ADMIN,
            45500.00,
            "Cobro Venta #101 en Efectivo",
            101
        );

        $this->assertNotNull($res);
        $this->assertEquals(10, $res['id_caja']);
        $this->assertEquals(45500.00, $res['monto']);
        $this->assertEquals(CajaMovimiento::TIPO_COBRO, $res['tipo']);
    }
}
