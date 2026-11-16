<?php

namespace Tests\Unit\Rendiciones;

use App\Modules\Rendiciones\Services\ProcesadorDeCobroRendicion;
use App\Modules\Rendiciones\Services\ProcesadorDeDevolucionRendicion;
use App\Modules\Rendiciones\Services\ProcesadorDeDiferenciaRendicion;
use App\Modules\Rendiciones\Services\ProcesadorDeValidacionRendicion;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias para los procesadores de lógica pura de Rendiciones (Grupo 2).
 */
class RendicionesProcesadoresTest extends TestCase
{
    private ProcesadorDeCobroRendicion $cobroProc;
    private ProcesadorDeDiferenciaRendicion $diferenciaProc;
    private ProcesadorDeDevolucionRendicion $devolucionProc;
    private ProcesadorDeValidacionRendicion $validacionProc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cobroProc = new ProcesadorDeCobroRendicion();
        $this->diferenciaProc = new ProcesadorDeDiferenciaRendicion();
        $this->devolucionProc = new ProcesadorDeDevolucionRendicion();
        $this->validacionProc = new ProcesadorDeValidacionRendicion();
    }

    // --- COBROS ---

    public function test_cobro_valido_retorna_exito(): void
    {
        $res = $this->cobroProc->registrarCobro([
            'id_rendicion' => 1,
            'id_cliente' => 10,
            'id_factura' => 20,
            'monto' => 15000.50,
            'medio_pago' => 'efectivo',
        ]);

        $this->assertFalse($res['error']);
        $this->assertEquals(201, $res['codigo']);
        $this->assertEquals(15000.50, $res['data']['monto']);
        $this->assertEquals('efectivo', $res['data']['medio_pago']);
    }

    public function test_cobro_rechaza_campos_obligatorios_faltantes(): void
    {
        $res = $this->cobroProc->registrarCobro([
            'id_rendicion' => 1,
            'monto' => 500,
        ]);

        $this->assertTrue($res['error']);
        $this->assertEquals(400, $res['codigo']);
        $this->assertStringContainsString('Falta el campo obligatorio', $res['mensaje']);
    }

    public function test_cobro_rechaza_monto_cero_o_negativo(): void
    {
        $res = $this->cobroProc->registrarCobro([
            'id_rendicion' => 1,
            'id_cliente' => 10,
            'id_factura' => 20,
            'monto' => 0,
            'medio_pago' => 'efectivo',
        ]);

        $this->assertTrue($res['error']);
        $this->assertStringContainsString('monto abonado es inválido', $res['mensaje']);
    }

    public function test_cobro_rechaza_medio_pago_invalido(): void
    {
        $res = $this->cobroProc->registrarCobro([
            'id_rendicion' => 1,
            'id_cliente' => 10,
            'id_factura' => 20,
            'monto' => 1000,
            'medio_pago' => 'cripto',
        ]);

        $this->assertTrue($res['error']);
        $this->assertStringContainsString('medio de pago seleccionado no es válido', $res['mensaje']);
    }

    // --- DIFERENCIAS ---

    public function test_diferencia_calcula_faltante_correctamente(): void
    {
        $res = $this->diferenciaProc->registrarDiferencia([
            'id_rendicion' => 2,
            'total_esperado' => 50000,
            'total_rendido' => 45000,
            'motivo' => 'Faltante de caja',
        ]);

        $this->assertFalse($res['error']);
        $this->assertEquals(-5000, $res['data']['diferencia']);
        $this->assertEquals('Faltante', $res['data']['tipo_diferencia']);
        $this->assertTrue($res['data']['diferencia_pendiente']);
    }

    public function test_diferencia_calcula_sobrante_correctamente(): void
    {
        $res = $this->diferenciaProc->registrarDiferencia([
            'id_rendicion' => 2,
            'total_esperado' => 40000,
            'total_rendido' => 42500,
        ]);

        $this->assertFalse($res['error']);
        $this->assertEquals(2500, $res['data']['diferencia']);
        $this->assertEquals('Sobrante', $res['data']['tipo_diferencia']);
        $this->assertTrue($res['data']['diferencia_pendiente']);
    }

    public function test_diferencia_exacta(): void
    {
        $res = $this->diferenciaProc->registrarDiferencia([
            'id_rendicion' => 2,
            'total_esperado' => 30000,
            'total_rendido' => 30000,
        ]);

        $this->assertFalse($res['error']);
        $this->assertEquals(0.0, $res['data']['diferencia']);
        $this->assertEquals('Exacto', $res['data']['tipo_diferencia']);
        $this->assertFalse($res['data']['diferencia_pendiente']);
    }

    // --- DEVOLUCIONES ---

    public function test_devolucion_valida_retorna_exito(): void
    {
        $res = $this->devolucionProc->registrarDevolucion([
            'id_rendicion' => 5,
            'id_pedido' => 120,
            'cliente' => 'Panadería Central',
            'motivo' => 'Local cerrado',
        ]);

        $this->assertFalse($res['error']);
        $this->assertEquals(201, $res['codigo']);
        $this->assertEquals(120, $res['data']['id_pedido']);
        $this->assertEquals('Local cerrado', $res['data']['motivo']);
        $this->assertEquals('no_entregada', $res['data']['estado_entrega']);
    }

    public function test_devolucion_rechaza_sin_motivo_o_pedido(): void
    {
        $res = $this->devolucionProc->registrarDevolucion([
            'id_rendicion' => 5,
        ]);

        $this->assertTrue($res['error']);
        $this->assertEquals(400, $res['codigo']);
    }

    // --- VALIDACIÓN / REVISIÓN ---

    public function test_validacion_aprobada_exitosa(): void
    {
        $res = $this->validacionProc->validarRendicion([
            'id_rendicion' => 8,
            'accion' => 'aprobada',
            'id_usuario_validador' => 1,
        ]);

        $this->assertFalse($res['error']);
        $this->assertEquals(200, $res['codigo']);
        $this->assertEquals('Aprobada', $res['data']['estado_actual']);
    }

    public function test_validacion_rechazada_exige_motivo(): void
    {
        $res = $this->validacionProc->validarRendicion([
            'id_rendicion' => 8,
            'accion' => 'rechazada',
            'id_usuario_validador' => 1,
            'motivo_rechazo' => '',
        ]);

        $this->assertTrue($res['error']);
        $this->assertStringContainsString('motivo de rechazo', $res['mensaje']);
    }
}
