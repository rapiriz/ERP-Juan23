<?php

namespace Tests\Feature\Ventas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaModuloVentas;
use Tests\TestCase;

/**
 * Tests de punta a punta:
 *  - La pantalla /historial-ventas recibe los flags de los botones.
 *  - Una venta recorre TODO su ciclo de vida usando las rutas reales.
 */
class HistorialYFlujoCompletoTest extends TestCase
{
    use PreparaModuloVentas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararModuloVentas();
        $this->withoutVite(); // la vista usa @vite; en tests no hace falta compilar CSS/JS
    }

    public function test_historial_web_recibe_los_flags_de_cada_venta(): void
    {
        $confirmada = $this->crearVenta('confirmada');
        $pagada = $this->crearVenta('pagada');
        $anulada = $this->crearVenta('cancelada');

        $this->get('/historial-ventas')
            ->assertOk()
            ->assertViewHas('ventas', function ($ventas) use ($confirmada, $pagada, $anulada) {
                $porId = collect($ventas)->keyBy('id');

                return $porId[$confirmada]['puede_modificar'] === true
                    && $porId[$confirmada]['puede_eliminar'] === true
                    && $porId[$confirmada]['anulada'] === false
                    && $porId[$pagada]['puede_modificar'] === false
                    && $porId[$pagada]['puede_eliminar'] === false
                    && $porId[$anulada]['anulada'] === true
                    && $porId[$anulada]['puede_restaurar'] === true
                    // lo que ya existía sigue estando
                    && $porId[$confirmada]['estado'] === 'confirmada'
                    && isset($porId[$confirmada]['detalle'], $porId[$confirmada]['factura']);
            });
    }

    public function test_ciclo_de_vida_completo_de_una_venta(): void
    {
        // 1) Se crea desde el POS (ruta web existente, VentaController@store).
        $this->postJson('/ventas', [
            'cliente_id' => self::CLIENTE,
            'lista' => 'minorista',
            'items' => [['id' => 1, 'cantidad' => 2, 'descuento' => 0]],
        ])->assertCreated();
        $id = 1;
        $this->assertSame(-5000.0, $this->saldoGuardado(self::CLIENTE));

        // 2) Se ve en la API con los botones habilitados.
        $this->getJson("/api/ventas/{$id}")
            ->assertOk()
            ->assertJsonPath('data.estado.valor', 'confirmada')
            ->assertJsonPath('data.acciones.puede_modificar', true);

        // 3) Se modifica: 1 Coca + 1 Pan = 4300.
        $this->putJson("/api/ventas/{$id}", [
            'lista' => 'minorista',
            'items' => [['id' => 1, 'cantidad' => 1, 'descuento' => 0], ['id' => 2, 'cantidad' => 1, 'descuento' => 0]],
        ])->assertOk()->assertJsonPath('data.total', 4300);
        $this->assertSame(-4300.0, $this->saldoGuardado(self::CLIENTE));

        // 4) Se anula: la deuda desaparece.
        $this->deleteJson("/api/ventas/{$id}", ['motivo' => 'Se equivocó de pedido'])->assertOk();
        $this->assertSame(0.0, $this->saldoGuardado(self::CLIENTE));

        // 5) Se restaura: vuelve la deuda.
        $this->postJson("/api/ventas/{$id}/restaurar")->assertOk()->assertJsonPath('data.estado.valor', 'confirmada');
        $this->assertSame(-4300.0, $this->saldoGuardado(self::CLIENTE));

        // 6) Avanza de fase hasta pagada.
        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'preparada'])->assertOk();
        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'entregada'])->assertOk();
        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'pagada'])->assertOk();

        // 7) Pagada: ya no se puede modificar ni eliminar.
        $this->putJson("/api/ventas/{$id}", ['lista' => 'minorista', 'items' => [['id' => 1, 'cantidad' => 9, 'descuento' => 0]]])->assertStatus(409);
        $this->deleteJson("/api/ventas/{$id}", ['motivo' => 'Ya no lo quiere'])->assertStatus(409);

        // 8) Se factura y queda cerrada.
        $this->patchJson("/api/ventas/{$id}/estado", ['estado' => 'facturada'])->assertOk();

        $this->getJson("/api/ventas/{$id}")
            ->assertJsonPath('data.estado.valor', 'facturada')
            ->assertJsonPath('data.total', 4300)
            ->assertJsonPath('data.acciones', ['puede_modificar' => false, 'puede_eliminar' => false, 'puede_restaurar' => false])
            ->assertJsonCount(7, 'data.historial'); // modificación, anulación, restauración y 4 cambios de estado
        $this->assertSame(-4300.0, $this->saldoGuardado(self::CLIENTE));
    }
}
