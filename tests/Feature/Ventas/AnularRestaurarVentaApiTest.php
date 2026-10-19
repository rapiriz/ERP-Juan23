<?php

namespace Tests\Feature\Ventas;

use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Integraciones\MockStockApi;
use App\Services\MockProductoApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaModuloVentas;
use Tests\TestCase;

/**
 * Tests de:
 *   DELETE /api/ventas/{id}             (botón "Eliminar" = anular)
 *   POST   /api/ventas/{id}/restaurar   (des-anular)
 */
class AnularRestaurarVentaApiTest extends TestCase
{
    use PreparaModuloVentas, RefreshDatabase;

    private MockStockApi $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararModuloVentas();

        $this->stock = new MockStockApi(new MockProductoApi);
        $this->app->instance(StockGateway::class, $this->stock);
    }

    private function anular(int $id, ?string $motivo = 'El cliente canceló el pedido')
    {
        return $this->deleteJson("/api/ventas/{$id}", $motivo === null ? [] : ['motivo' => $motivo]);
    }

    // ------------------------------------------------------------------
    // ANULAR
    // ------------------------------------------------------------------

    public function test_anular_no_borra_la_venta_la_marca_cancelada(): void
    {
        $id = $this->crearVenta();

        $this->anular($id)
            ->assertOk()
            ->assertJsonPath('message', 'Venta anulada. Se puede restaurar desde el historial.')
            ->assertJsonPath('data.estado.valor', 'cancelada')
            ->assertJsonPath('data.anulada', true)
            ->assertJsonPath('data.acciones', ['puede_modificar' => false, 'puede_eliminar' => false, 'puede_restaurar' => true])
            ->assertJsonPath('data.historial.0.accion', 'anulacion')
            ->assertJsonPath('data.historial.0.estado_anterior', 'confirmada')
            ->assertJsonPath('data.historial.0.motivo', 'El cliente canceló el pedido');

        // La venta y su detalle siguen existiendo.
        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'estado' => 'cancelada', 'total' => 5000]);
        $this->assertDatabaseHas('detalle_venta', ['id_venta' => $id, 'id_producto' => 1]);
    }

    public function test_anular_quita_la_deuda_pero_deja_el_movimiento_visible(): void
    {
        $id = $this->crearVenta();
        $this->assertSame(-5000.0, $this->saldoGuardado(self::CLIENTE));

        $this->anular($id)->assertOk();

        $movimiento = $this->movimientoDe($id);
        $this->assertEquals(0, $movimiento->importe);
        $this->assertSame('venta_anulada', $movimiento->tipo);
        $this->assertSame("Venta POS #{$id} (ANULADA)", $movimiento->descripcion);
        $this->assertSame(0.0, $this->saldoGuardado(self::CLIENTE));
    }

    public function test_anular_devuelve_la_mercaderia_al_stock(): void
    {
        $id = $this->crearVenta('confirmada', [[1, 2, 2500, 0], [1, 1, 2500, 0], [6, 4, 950, 0]]);

        $this->anular($id)->assertOk();

        $this->assertSame([['venta_id' => $id, 'motivo' => 'anulacion', 'cambios' => [1 => 3, 6 => 4]]], $this->stock->avisos());
    }

    #[DataProvider('estadosAnulables')]
    public function test_se_puede_anular_antes_del_pago(string $estado): void
    {
        $id = $this->crearVenta($estado);

        $this->anular($id)->assertOk();
        $this->assertSame('cancelada', $this->estadoDe($id));
    }

    public static function estadosAnulables(): array
    {
        return ['pendiente' => ['pendiente'], 'confirmada' => ['confirmada'], 'preparada' => ['preparada'], 'entregada' => ['entregada']];
    }

    #[DataProvider('estadosNoAnulables')]
    public function test_no_se_puede_anular_despues_del_pago(string $estado): void
    {
        $id = $this->crearVenta($estado);

        $this->anular($id)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'VENTA_NO_EDITABLE')
            ->assertJsonPath('accion', 'eliminar')
            ->assertJsonPath('estado_actual', $estado);

        $this->assertSame($estado, $this->estadoDe($id));
        $this->assertEquals(-5000, $this->movimientoDe($id)->importe);
        $this->assertDatabaseCount('venta_historial', 0);
    }

    public static function estadosNoAnulables(): array
    {
        return ['pagada' => ['pagada'], 'facturada' => ['facturada']];
    }

    public function test_anular_dos_veces_da_409(): void
    {
        $id = $this->crearVenta();
        $this->anular($id)->assertOk();

        $this->anular($id)
            ->assertStatus(409)
            ->assertJsonPath('message', "La venta #{$id} ya está anulada.");

        $this->assertDatabaseCount('venta_historial', 1);
    }

    #[DataProvider('motivosInvalidos')]
    public function test_el_motivo_es_obligatorio(?string $motivo): void
    {
        $id = $this->crearVenta();

        $this->anular($id, $motivo)->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $this->assertSame('confirmada', $this->estadoDe($id));
    }

    public static function motivosInvalidos(): array
    {
        return [
            'sin motivo' => [null],
            'vacío' => [''],
            'solo espacios' => ['        '],
            'muy corto' => ['abc'],
            'muy largo' => [str_repeat('a', 151)],
        ];
    }

    public function test_anular_venta_inexistente(): void
    {
        $this->anular(999)->assertNotFound()->assertJsonPath('codigo', 'VENTA_NO_ENCONTRADA');
    }

    public function test_error_de_base_al_anular_deshace_todo(): void
    {
        $id = $this->crearVenta();
        Schema::drop('venta_historial');

        $this->anular($id)->assertStatus(500);

        $this->assertSame('confirmada', $this->estadoDe($id));
        $this->assertEquals(-5000, $this->movimientoDe($id)->importe);
        $this->assertSame([], $this->stock->avisos());
    }

    public function test_log_de_anulacion(): void
    {
        $id = $this->crearVenta();
        $this->anular($id)->assertOk();

        $log = $this->logVentas();
        $this->assertStringContainsString('ANULAR VENTA', $log);
        $this->assertStringContainsString("Endpoint ......: DELETE /api/ventas/{$id}", $log);
        $this->assertStringContainsString('[PASO 6/6] Avisar al módulo de Stock', $log);
        $this->assertStringContainsString('VENTA ANULADA  ·  Respuesta HTTP 200 OK', $log);
    }

    // ------------------------------------------------------------------
    // RESTAURAR
    // ------------------------------------------------------------------

    public function test_restaurar_vuelve_al_estado_anterior_y_devuelve_la_deuda(): void
    {
        $id = $this->crearVenta('preparada');
        $this->anular($id)->assertOk();

        $this->postJson("/api/ventas/{$id}/restaurar")
            ->assertOk()
            ->assertJsonPath('message', 'Venta restaurada.')
            ->assertJsonPath('data.estado.valor', 'preparada')
            ->assertJsonPath('data.anulada', false)
            ->assertJsonPath('data.acciones.puede_modificar', true)
            ->assertJsonPath('data.historial.1.accion', 'restauracion')
            ->assertJsonPath('data.historial.1.estado_nuevo', 'preparada');

        $movimiento = $this->movimientoDe($id);
        $this->assertEquals(-5000, $movimiento->importe);
        $this->assertSame('venta', $movimiento->tipo);
        $this->assertSame("Venta POS #{$id}", $movimiento->descripcion);
        $this->assertSame(-5000.0, $this->saldoGuardado(self::CLIENTE));
    }

    public function test_restaurar_vuelve_a_descontar_el_stock(): void
    {
        $id = $this->crearVenta();
        $this->anular($id);

        $this->postJson("/api/ventas/{$id}/restaurar")->assertOk();

        $this->assertSame([1 => -2], $this->stock->avisos()[1]['cambios']);
        $this->assertSame('restauracion', $this->stock->avisos()[1]['motivo']);
    }

    public function test_restaurar_venta_no_anulada_da_409(): void
    {
        $id = $this->crearVenta('confirmada');

        $this->postJson("/api/ventas/{$id}/restaurar")
            ->assertStatus(409)
            ->assertJsonPath('accion', 'restaurar')
            ->assertJsonPath('message', "La venta #{$id} no está anulada (está confirmada).");
    }

    public function test_restaurar_venta_anulada_fuera_del_sistema_vuelve_a_pendiente(): void
    {
        // Cancelada directo en la base: no hay historial que diga el estado anterior.
        $id = $this->crearVenta('cancelada');

        $this->postJson("/api/ventas/{$id}/restaurar")->assertOk()->assertJsonPath('data.estado.valor', 'pendiente');
    }

    public function test_anular_y_restaurar_varias_veces_usa_siempre_el_ultimo_estado(): void
    {
        $id = $this->crearVenta('pendiente');

        $this->anular($id);
        $this->postJson("/api/ventas/{$id}/restaurar")->assertJsonPath('data.estado.valor', 'pendiente');
        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'entregada'])->assertOk();
        $this->anular($id);
        $this->postJson("/api/ventas/{$id}/restaurar")->assertJsonPath('data.estado.valor', 'entregada');

        $this->assertDatabaseCount('venta_historial', 5);
        $this->assertSame(-5000.0, $this->saldoGuardado(self::CLIENTE));
    }

    public function test_restaurar_venta_inexistente(): void
    {
        $this->postJson('/api/ventas/999/restaurar')->assertNotFound();
    }

    public function test_venta_restaurada_se_puede_volver_a_modificar(): void
    {
        $id = $this->crearVenta();
        $this->anular($id);
        $this->postJson("/api/ventas/{$id}/restaurar")->assertOk();

        $this->putJson("/api/ventas/{$id}", ['lista' => 'minorista', 'items' => [['id' => 6, 'cantidad' => 1, 'descuento' => 0]]])
            ->assertOk()
            ->assertJsonPath('data.total', 950);

        $this->assertEquals(-950, DB::table('cuenta_corriente_movimientos')->where('venta_id', $id)->value('importe'));
    }
}
