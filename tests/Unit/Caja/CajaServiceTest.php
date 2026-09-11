<?php

namespace Tests\Unit\Caja;

use App\Caja\Models\Caja;
use App\Caja\Models\CajaMovimiento;
use App\Caja\Repositories\CajaRepository;
use App\Caja\Services\CajaService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios de CajaService: se mockea CajaRepository (sin BD real), así
 * se testea solo la lógica de negocio. Correr con:
 *   vendor/bin/phpunit tests/Unit/Caja/CajaServiceTest.php
 * No pude ejecutarlos yo (sin PHP disponible en mi entorno) — avisar si algo
 * no se comporta como se espera.
 */
class CajaServiceTest extends TestCase
{
    private function cajaFake(array $attrs): Caja
    {
        $caja = new Caja($attrs);
        if (isset($attrs['id_caja'])) {
            $caja->id_caja = $attrs['id_caja'];
        }
        return $caja;
    }

    // ---------- abrirCaja ----------

    public function test_abrir_caja_crea_caja_cuando_no_hay_una_abierta(): void
    {
        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarAbiertaDeUsuario')->willReturn(null);
        $repo->expects($this->once())
            ->method('crear')
            ->willReturn($this->cajaFake(['id_caja' => 1, 'id_usuario' => 7, 'monto_inicial' => 100.0, 'estado' => Caja::ESTADO_ABIERTA]));

        $resultado = (new CajaService($repo))->abrirCaja(7, 100.0);

        $this->assertArrayNotHasKey('error', $resultado);
        $this->assertEquals(7, $resultado['id_usuario']);
    }

    public function test_abrir_caja_devuelve_error_si_ya_hay_una_abierta_ese_dia(): void
    {
        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarAbiertaDeUsuario')
            ->willReturn($this->cajaFake(['id_caja' => 1, 'id_usuario' => 7, 'estado' => Caja::ESTADO_ABIERTA]));
        $repo->expects($this->never())->method('crear');

        $resultado = (new CajaService($repo))->abrirCaja(7, 100.0);

        $this->assertTrue($resultado['error']);
        $this->assertSame('Ya existe una caja abierta hoy para este usuario.', $resultado['mensaje']);
    }

    public function test_abrir_caja_devuelve_error_si_monto_inicial_es_negativo(): void
    {
        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarAbiertaDeUsuario')->willReturn(null);
        $repo->expects($this->never())->method('crear');

        $resultado = (new CajaService($repo))->abrirCaja(7, -10.0);

        $this->assertTrue($resultado['error']);
    }

    // ---------- cerrarCaja ----------

    public function test_cerrar_caja_calcula_saldo_esperado_y_diferencia_correctamente(): void
    {
        $caja = $this->cajaFake(['id_caja' => 5, 'id_usuario' => 7, 'monto_inicial' => 100.0, 'estado' => Caja::ESTADO_ABIERTA]);

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->with(5)->willReturn($caja);
        $repo->method('calcularSaldoMovimientos')->with(5)->willReturn(50.0);
        $repo->method('guardar')->willReturnArgument(0);

        $resultado = (new CajaService($repo))->cerrarCaja(5, 7, 160.0);

        // saldoEsperado = 100 (inicial) + 50 (movimientos) = 150
        // diferencia = montoFinal(160) - saldoEsperado(150) = 10
        $this->assertEquals(10.0, (float) $resultado['diferencia']);
        $this->assertEquals(Caja::ESTADO_CERRADA, $resultado['estado']);
        $this->assertEquals(7, $resultado['id_usuario_cierre']);
    }

    public function test_cerrar_caja_devuelve_null_si_no_existe(): void
    {
        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->willReturn(null);

        $resultado = (new CajaService($repo))->cerrarCaja(999, 7, 100.0);

        $this->assertNull($resultado);
    }

    public function test_cerrar_caja_devuelve_error_si_no_pertenece_al_usuario(): void
    {
        $caja = $this->cajaFake(['id_caja' => 5, 'id_usuario' => 7, 'estado' => Caja::ESTADO_ABIERTA]);

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->willReturn($caja);

        // usuario logueado (99) distinto al dueño de la caja (7)
        $resultado = (new CajaService($repo))->cerrarCaja(5, 99, 100.0);

        $this->assertTrue($resultado['error']);
        $this->assertSame('No podés cerrar la caja de otro usuario.', $resultado['mensaje']);
    }

    public function test_cerrar_caja_devuelve_error_si_ya_esta_cerrada(): void
    {
        $caja = $this->cajaFake(['id_caja' => 5, 'id_usuario' => 7, 'estado' => Caja::ESTADO_CERRADA]);

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->willReturn($caja);

        $resultado = (new CajaService($repo))->cerrarCaja(5, 7, 100.0);

        $this->assertTrue($resultado['error']);
        $this->assertSame('La caja ya está cerrada.', $resultado['mensaje']);
    }

    // ---------- registrarMovimiento ----------

    public function test_registrar_movimiento_crea_movimiento_cuando_caja_abierta_y_propia(): void
    {
        $caja = $this->cajaFake(['id_caja' => 5, 'id_usuario' => 7, 'estado' => Caja::ESTADO_ABIERTA]);

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->with(5)->willReturn($caja);
        $repo->expects($this->once())
            ->method('crearMovimiento')
            ->with($this->callback(fn (array $d) => $d['id_caja'] === 5 && $d['tipo'] === CajaMovimiento::TIPO_EGRESO && $d['monto'] === 25.0))
            ->willReturn(new CajaMovimiento(['id_caja' => 5, 'tipo' => CajaMovimiento::TIPO_EGRESO, 'monto' => 25.0, 'concepto' => 'Compra de bolsas']));

        $resultado = (new CajaService($repo))->registrarMovimiento(5, 7, CajaMovimiento::TIPO_EGRESO, 'Compra de bolsas', 25.0);

        $this->assertEquals(CajaMovimiento::TIPO_EGRESO, $resultado['tipo']);
    }

    public function test_registrar_movimiento_devuelve_error_si_caja_no_es_propia(): void
    {
        $caja = $this->cajaFake(['id_caja' => 5, 'id_usuario' => 7, 'estado' => Caja::ESTADO_ABIERTA]);

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->willReturn($caja);
        $repo->expects($this->never())->method('crearMovimiento');

        $resultado = (new CajaService($repo))->registrarMovimiento(5, 99, CajaMovimiento::TIPO_EGRESO, 'Test', 10.0);

        $this->assertTrue($resultado['error']);
    }

    public function test_registrar_movimiento_devuelve_error_si_caja_cerrada(): void
    {
        $caja = $this->cajaFake(['id_caja' => 5, 'id_usuario' => 7, 'estado' => Caja::ESTADO_CERRADA]);

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->willReturn($caja);
        $repo->expects($this->never())->method('crearMovimiento');

        $resultado = (new CajaService($repo))->registrarMovimiento(5, 7, CajaMovimiento::TIPO_EGRESO, 'Test', 10.0);

        $this->assertTrue($resultado['error']);
    }

    public function test_registrar_movimiento_devuelve_error_si_tipo_invalido(): void
    {
        $caja = $this->cajaFake(['id_caja' => 5, 'id_usuario' => 7, 'estado' => Caja::ESTADO_ABIERTA]);

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->willReturn($caja);
        $repo->expects($this->never())->method('crearMovimiento');

        $resultado = (new CajaService($repo))->registrarMovimiento(5, 7, 'tipo_inventado', 'Test', 10.0);

        $this->assertTrue($resultado['error']);
    }

    public function test_registrar_movimiento_devuelve_error_si_monto_no_es_positivo(): void
    {
        $caja = $this->cajaFake(['id_caja' => 5, 'id_usuario' => 7, 'estado' => Caja::ESTADO_ABIERTA]);

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarPorId')->willReturn($caja);
        $repo->expects($this->never())->method('crearMovimiento');

        $resultado = (new CajaService($repo))->registrarMovimiento(5, 7, CajaMovimiento::TIPO_EGRESO, 'Test', 0.0);

        $this->assertTrue($resultado['error']);
    }

    // ---------- modificarMovimiento ----------

    public function test_modificar_movimiento_devuelve_error_si_monto_no_es_positivo(): void
    {
        $movimiento = new CajaMovimiento(['id_caja' => 5, 'tipo' => CajaMovimiento::TIPO_EGRESO, 'monto' => 10.0, 'concepto' => 'X']);
        $movimiento->id_movimiento_caja = 1;

        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarMovimientoPorId')->willReturn($movimiento);
        $repo->expects($this->never())->method('guardarMovimiento');

        $resultado = (new CajaService($repo))->modificarMovimiento(1, 'Nuevo concepto', -5.0);

        $this->assertTrue($resultado['error']);
    }

    public function test_modificar_movimiento_devuelve_null_si_no_existe(): void
    {
        $repo = $this->createMock(CajaRepository::class);
        $repo->method('buscarMovimientoPorId')->willReturn(null);

        $resultado = (new CajaService($repo))->modificarMovimiento(999, 'X', 10.0);

        $this->assertNull($resultado);
    }
}
