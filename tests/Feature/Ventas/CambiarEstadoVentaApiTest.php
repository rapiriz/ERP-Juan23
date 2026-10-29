<?php

namespace Tests\Feature\Ventas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaModuloVentas;
use Tests\TestCase;

/**
 * Tests de PATCH /api/ventas/{id}/estado (avanzar de fase).
 */
class CambiarEstadoVentaApiTest extends TestCase
{
    use PreparaModuloVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararModuloVentas();
    }

    private function cambiar(int $id, string $estado, array $extra = [])
    {
        return $this->patchJson("/api/ventas/{$id}/estado", ['estado' => $estado] + $extra);
    }

    #[DataProvider('transicionesValidas')]
    public function test_transiciones_permitidas(string $desde, string $hacia): void
    {
        $id = $this->crearVenta($desde);

        $this->cambiar($id, $hacia)
            ->assertOk()
            ->assertJsonPath('message', "La venta pasó a \"{$hacia}\".")
            ->assertJsonPath('data.estado.valor', $hacia)
            ->assertJsonPath('data.historial.0.accion', 'cambio_estado')
            ->assertJsonPath('data.historial.0.estado_anterior', $desde)
            ->assertJsonPath('data.historial.0.estado_nuevo', $hacia);

        $this->assertSame($hacia, $this->estadoDe($id));
        // Cambiar de fase NO toca la cuenta corriente.
        $this->assertEquals(-5000, $this->movimientoDe($id)->importe);
    }

    public static function transicionesValidas(): array
    {
        return [
            'pendiente → confirmada' => ['pendiente', 'confirmada'],
            'confirmada → preparada' => ['confirmada', 'preparada'],
            'preparada → entregada' => ['preparada', 'entregada'],
            'entregada → pagada' => ['entregada', 'pagada'],
            'pagada → facturada' => ['pagada', 'facturada'],
            'saltear: confirmada → pagada (cobro en mostrador)' => ['confirmada', 'pagada'],
            'saltear: pendiente → entregada' => ['pendiente', 'entregada'],
        ];
    }

    #[DataProvider('transicionesInvalidas')]
    public function test_transiciones_no_permitidas(string $desde, string $hacia): void
    {
        $id = $this->crearVenta($desde);

        $this->cambiar($id, $hacia)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'TRANSICION_INVALIDA')
            ->assertJsonPath('estado_actual', $desde)
            ->assertJsonPath('estado_pedido', $hacia);

        $this->assertSame($desde, $this->estadoDe($id));
        $this->assertDatabaseCount('venta_historial', 0);
    }

    public static function transicionesInvalidas(): array
    {
        return [
            'volver atrás: pagada → confirmada' => ['pagada', 'confirmada'],
            'volver atrás: entregada → preparada' => ['entregada', 'preparada'],
            'facturar sin pagar' => ['confirmada', 'facturada'],
            'facturada es final' => ['facturada', 'pagada'],
            'cancelada solo se restaura' => ['cancelada', 'confirmada'],
            'mismo estado' => ['preparada', 'preparada'],
            'anular por PATCH' => ['confirmada', 'cancelada'],
        ];
    }

    public function test_la_respuesta_409_dice_a_que_estados_si_se_puede_pasar(): void
    {
        $id = $this->crearVenta('preparada');

        $this->cambiar($id, 'pendiente')->assertJsonPath('transiciones_posibles', ['entregada', 'pagada']);
    }

    public function test_anular_por_patch_explica_que_hay_que_usar_delete(): void
    {
        $id = $this->crearVenta();

        $this->cambiar($id, 'cancelada')
            ->assertStatus(409)
            ->assertJsonPath('message', "Para anular una venta usá DELETE /api/ventas/{$id} (pide motivo y corrige la cuenta corriente).");
    }

    public function test_mismo_estado_explica_que_ya_esta(): void
    {
        $id = $this->crearVenta('preparada');

        $this->cambiar($id, 'preparada')->assertJsonPath('message', "La venta #{$id} ya está en estado \"preparada\".");
    }

    public function test_recorrido_completo_de_fases(): void
    {
        $id = $this->crearVenta('pendiente');

        foreach (['confirmada', 'preparada', 'entregada', 'pagada', 'facturada'] as $estado) {
            $this->cambiar($id, $estado)->assertOk();
        }

        $this->getJson("/api/ventas/{$id}")
            ->assertJsonPath('data.estado.valor', 'facturada')
            ->assertJsonCount(5, 'data.historial')
            ->assertJsonPath('data.transiciones_posibles', []);
    }

    public function test_una_vez_pagada_ya_no_se_puede_modificar_ni_eliminar(): void
    {
        $id = $this->crearVenta();
        $this->cambiar($id, 'pagada')->assertOk()->assertJsonPath('data.acciones.puede_modificar', false);

        $this->putJson("/api/ventas/{$id}", ['lista' => 'minorista', 'items' => [['id' => 1, 'cantidad' => 1, 'descuento' => 0]]])->assertStatus(409);
        $this->deleteJson("/api/ventas/{$id}", ['motivo' => 'Ya no lo quiere'])->assertStatus(409);
    }

    public function test_el_motivo_queda_en_el_historial(): void
    {
        $id = $this->crearVenta();

        $this->cambiar($id, 'pagada', ['motivo' => 'Cobrado en efectivo'])->assertJsonPath('data.historial.0.motivo', 'Cobrado en efectivo');
    }

    public function test_acepta_mayusculas_y_espacios(): void
    {
        $id = $this->crearVenta();

        $this->cambiar($id, '  PAGADA ')->assertOk()->assertJsonPath('data.estado.valor', 'pagada');
    }

    public function test_estado_inexistente_o_faltante_es_422(): void
    {
        $id = $this->crearVenta();

        $this->cambiar($id, 'volando')->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->patchJson("/api/ventas/{$id}/estado", [])->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->cambiar($id, 'pagada', ['motivo' => str_repeat('a', 151)])->assertUnprocessable()->assertJsonValidationErrors('motivo');
    }

    public function test_venta_con_estado_desconocido_en_la_base(): void
    {
        $id = $this->crearVenta('raro');

        $this->cambiar($id, 'pagada')->assertStatus(409)->assertJsonPath('estado_actual', 'raro');
    }

    public function test_venta_inexistente(): void
    {
        $this->cambiar(999, 'pagada')->assertNotFound();
    }

    public function test_log_de_cambio_de_estado(): void
    {
        $id = $this->crearVenta();
        $this->cambiar($id, 'preparada')->assertOk();

        $log = $this->logVentas();
        $this->assertStringContainsString('CAMBIAR ESTADO DE VENTA', $log);
        $this->assertStringContainsString('Transición permitida: confirmada → preparada', $log);
        $this->assertStringContainsString('[PASO 4/4] Registrar en el historial', $log);
        $this->assertStringContainsString('ESTADO CAMBIADO  ·  Respuesta HTTP 200 OK', $log);
    }
}
