<?php

namespace Tests\Feature\Ventas;

use App\Contracts\Integraciones\ProductoGateway;
use App\Contracts\Integraciones\StockGateway;
use App\Exceptions\Ventas\IntegracionNoDisponibleException;
use App\Services\Integraciones\MockStockApi;
use App\Services\MockProductoApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\PreparaModuloVentas;
use Tests\TestCase;

/**
 * Tests de PUT /api/ventas/{id} (botón "Modificar").
 */
class ModificarVentaApiTest extends TestCase
{
    use PreparaModuloVentas, RefreshDatabase;

    private MockStockApi $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararModuloVentas();

        // Stock simulado propio del test, para poder ver qué avisos recibió.
        $this->stock = new MockStockApi(new MockProductoApi);
        $this->app->instance(StockGateway::class, $this->stock);
    }

    /**
     * Pedido válido de modificación; $cambios pisa o agrega campos.
     * Por defecto: 3 x Pan lactal (1800) con $100 de descuento c/u = 5100.
     */
    private function cambios(array $cambios = []): array
    {
        return array_replace([
            'lista' => 'minorista',
            'items' => [['id' => 2, 'cantidad' => 3, 'descuento' => 100]],
        ], $cambios);
    }

    /**
     * Comprueba que la venta quedó EXACTAMENTE como estaba (2 x Coca-Cola = 5000).
     */
    private function assertVentaSinCambios(int $id): void
    {
        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'total' => 5000]);
        $this->assertSame(1, DB::table('detalle_venta')->where('id_venta', $id)->count());
        $this->assertDatabaseHas('detalle_venta', ['id_venta' => $id, 'id_producto' => 1, 'cantidad' => 2]);
        $this->assertEquals(-5000, $this->movimientoDe($id)->importe);
        $this->assertDatabaseCount('venta_historial', 0);
        $this->assertSame([], $this->stock->avisos());
    }

    // ------------------------------------------------------------------
    // Casos que salen bien
    // ------------------------------------------------------------------

    public function test_modifica_recalcula_y_actualiza_todo(): void
    {
        $id = $this->crearVenta();
        $comprobante = DB::table('venta')->where('id_venta', $id)->value('numFactura');

        $this->putJson("/api/ventas/{$id}", $this->cambios())
            ->assertOk()
            ->assertJsonPath('message', 'Venta modificada correctamente.')
            ->assertJsonPath('data.total', 5100)
            ->assertJsonPath('data.numero_comprobante', $comprobante) // no cambia
            ->assertJsonPath('data.fecha', '2026-10-13')               // no cambia
            ->assertJsonCount(1, 'data.detalles')
            ->assertJsonPath('data.detalles.0.producto', 'Pan lactal grande')
            ->assertJsonPath('data.historial.0.accion', 'modificacion')
            ->assertJsonPath('data.historial.0.datos', ['total_anterior' => 5000, 'total_nuevo' => 5100]);

        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'total' => 5100, 'estado' => 'confirmada', 'id_cliente' => self::CLIENTE]);
        $this->assertDatabaseMissing('detalle_venta', ['id_venta' => $id, 'id_producto' => 1]);
        $this->assertDatabaseHas('detalle_venta', ['id_venta' => $id, 'id_producto' => 2, 'cantidad' => 3, 'precio_unitario' => 1800, 'descuento' => 100, 'subtotal' => 5100]);
        $this->assertEquals(-5100, $this->movimientoDe($id)->importe);
        $this->assertSame(-5100.0, $this->saldoGuardado(self::CLIENTE));
    }

    public function test_mayorista_con_descuento_global(): void
    {
        $id = $this->crearVenta();

        // 2 x 1600 = 3200 → 10% = 320 → 2880
        $this->putJson("/api/ventas/{$id}", $this->cambios([
            'lista' => 'mayorista',
            'items' => [['id' => 2, 'cantidad' => 2, 'descuento' => 0]],
            'descuento_global' => ['modo' => 'porcentaje', 'valor' => 10],
        ]))
            ->assertOk()
            ->assertJsonPath('data.total', 2880)
            ->assertJsonPath('data.descuento_global', 320);
    }

    public function test_ignora_precios_y_totales_que_manda_el_front(): void
    {
        $id = $this->crearVenta();

        $this->putJson("/api/ventas/{$id}", $this->cambios([
            'total' => 1,
            'items' => [['id' => 1, 'cantidad' => 1, 'descuento' => 0, 'precio' => 1]],
        ]))->assertOk()->assertJsonPath('data.total', 2500);
    }

    public function test_observaciones(): void
    {
        $id = $this->crearVenta();

        // No vienen → se mantienen.
        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertJsonPath('data.observaciones', 'Venta de prueba');
        // Vienen con espacios → se recortan.
        $this->putJson("/api/ventas/{$id}", $this->cambios(['observaciones' => '  Entregar lunes  ']))->assertJsonPath('data.observaciones', 'Entregar lunes');
        // Vienen vacías → texto por defecto.
        $this->putJson("/api/ventas/{$id}", $this->cambios(['observaciones' => '   ']))->assertJsonPath('data.observaciones', 'Venta POS a cuenta corriente');
    }

    #[DataProvider('estadosEditables')]
    public function test_se_puede_modificar_antes_del_pago(string $estado): void
    {
        $id = $this->crearVenta($estado);

        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertOk()->assertJsonPath('data.estado.valor', $estado);
    }

    public static function estadosEditables(): array
    {
        return ['pendiente' => ['pendiente'], 'confirmada' => ['confirmada'], 'preparada' => ['preparada'], 'entregada' => ['entregada']];
    }

    public function test_venta_vieja_sin_movimiento_de_cuenta_corriente_lo_crea(): void
    {
        $id = $this->crearVenta('confirmada', [[1, 2, 2500, 0]], self::CLIENTE, false);

        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertOk();

        $movimiento = $this->movimientoDe($id);
        $this->assertEquals(-5100, $movimiento->importe);
        $this->assertSame(self::CLIENTE, (int) $movimiento->cliente_id);
    }

    public function test_modificar_dos_veces_no_duplica_el_movimiento(): void
    {
        $id = $this->crearVenta();

        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertOk();
        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertOk(); // mismos valores

        $this->assertSame(1, DB::table('cuenta_corriente_movimientos')->where('venta_id', $id)->count());
    }

    // ------------------------------------------------------------------
    // Stock
    // ------------------------------------------------------------------

    public function test_avisa_a_stock_solo_las_diferencias(): void
    {
        $id = $this->crearVenta(); // 2 x producto 1

        // Ahora: 5 x producto 1 y 1 x producto 2 → salen 3 del 1 y 1 del 2.
        $this->putJson("/api/ventas/{$id}", $this->cambios([
            'items' => [['id' => 1, 'cantidad' => 5, 'descuento' => 0], ['id' => 2, 'cantidad' => 1, 'descuento' => 0]],
        ]))->assertOk();

        $this->assertSame([['venta_id' => $id, 'motivo' => 'modificacion', 'cambios' => [1 => -3, 2 => -1]]], $this->stock->avisos());
    }

    public function test_sacar_un_producto_lo_devuelve_al_stock(): void
    {
        $id = $this->crearVenta(); // 2 x producto 1

        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertOk(); // ahora solo producto 2

        $this->assertSame([1 => 2, 2 => -3], $this->stock->avisos()[0]['cambios']);
    }

    public function test_si_las_cantidades_no_cambian_no_se_avisa_a_stock(): void
    {
        $id = $this->crearVenta();

        $this->putJson("/api/ventas/{$id}", $this->cambios(['items' => [['id' => 1, 'cantidad' => 2, 'descuento' => 500]]]))->assertOk();

        $this->assertSame([], $this->stock->avisos());
    }

    public function test_si_stock_falla_la_venta_se_guarda_igual_y_queda_en_el_log(): void
    {
        $this->app->instance(StockGateway::class, new class implements StockGateway
        {
            public function ajustar(array $cambios, int $ventaId, string $motivo): void
            {
                throw new RuntimeException('stock caído');
            }

            public function disponible(int $idProducto): ?int
            {
                return null;
            }
        });
        $id = $this->crearVenta();

        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertOk();

        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'total' => 5100]);
        $this->assertStringContainsString('⚠ Stock no respondió (stock caído)', $this->logVentas());
    }

    // ------------------------------------------------------------------
    // 409 - el estado no lo permite (no cambia NADA)
    // ------------------------------------------------------------------

    #[DataProvider('estadosCerrados')]
    public function test_no_se_puede_modificar_despues_del_pago_ni_anulada(string $estado): void
    {
        $id = $this->crearVenta($estado);

        $this->putJson("/api/ventas/{$id}", $this->cambios())
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'VENTA_NO_EDITABLE')
            ->assertJsonPath('accion', 'modificar')
            ->assertJsonPath('estado_actual', $estado)
            ->assertJsonPath('message', "La venta #{$id} está {$estado}: no se puede modificar.");

        $this->assertVentaSinCambios($id);
    }

    public static function estadosCerrados(): array
    {
        return ['pagada' => ['pagada'], 'facturada' => ['facturada'], 'cancelada' => ['cancelada']];
    }

    public function test_estado_desconocido_bloquea_la_modificacion(): void
    {
        $id = $this->crearVenta('raro');

        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertStatus(409)->assertJsonPath('estado_actual', 'raro');
    }

    // ------------------------------------------------------------------
    // 404
    // ------------------------------------------------------------------

    public function test_venta_inexistente(): void
    {
        $this->putJson('/api/ventas/999', $this->cambios())->assertNotFound()->assertJsonPath('codigo', 'VENTA_NO_ENCONTRADA');
    }

    // ------------------------------------------------------------------
    // 422 - datos inválidos (no cambia NADA)
    // ------------------------------------------------------------------

    #[DataProvider('datosInvalidos')]
    public function test_datos_invalidos(array $cambios, string $campo): void
    {
        $id = $this->crearVenta();

        $this->putJson("/api/ventas/{$id}", $this->cambios($cambios))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campo);

        $this->assertVentaSinCambios($id);
    }

    public static function datosInvalidos(): array
    {
        $item = fn (array $c) => ['items' => [array_replace(['id' => 1, 'cantidad' => 1, 'descuento' => 0], $c)]];

        return [
            'cambiar el cliente' => [['cliente_id' => 638204], 'cliente_id'],
            'sin lista' => [['lista' => null], 'lista'],
            'lista inexistente' => [['lista' => 'vip'], 'lista'],
            'sin productos' => [['items' => []], 'items'],
            'más de 100 productos' => [['items' => array_fill(0, 101, ['id' => 1, 'cantidad' => 1, 'descuento' => 0])], 'items'],
            'producto inexistente' => [$item(['id' => 77]), 'items.0.id'],
            'cantidad 0' => [$item(['cantidad' => 0]), 'items.0.cantidad'],
            'cantidad decimal' => [$item(['cantidad' => 1.5]), 'items.0.cantidad'],
            'descuento negativo' => [$item(['descuento' => -1]), 'items.0.descuento'],
            'descuento mayor al precio' => [$item(['descuento' => 2501]), 'items.0.descuento'],
            'porcentaje mayor a 100' => [['descuento_global' => ['modo' => 'porcentaje', 'valor' => 101]], 'descuento_global.valor'],
            'modo de descuento inválido' => [['descuento_global' => ['modo' => 'regalo', 'valor' => 1]], 'descuento_global.modo'],
            'total cero' => [['descuento_global' => ['modo' => 'porcentaje', 'valor' => 100]], 'items'],
            'observaciones largas' => [['observaciones' => str_repeat('a', 151)], 'observaciones'],
        ];
    }

    public function test_mensaje_claro_al_intentar_cambiar_el_cliente(): void
    {
        $id = $this->crearVenta();

        $this->putJson("/api/ventas/{$id}", $this->cambios(['cliente_id' => 638204]))
            ->assertJsonValidationErrors(['cliente_id' => 'El cliente de una venta no se puede cambiar. Anulá la venta y creá una nueva.']);
    }

    // ------------------------------------------------------------------
    // 500 / 503 - errores del sistema (no cambia NADA)
    // ------------------------------------------------------------------

    public function test_error_de_base_de_datos_devuelve_500_y_deshace_todo(): void
    {
        config(['app.debug' => false]);
        $id = $this->crearVenta();
        Schema::drop('venta_historial'); // falla el último paso de la transacción

        $this->putJson("/api/ventas/{$id}", $this->cambios())
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Hubo un error al procesar la venta. No se guardó ningún cambio.', 'detalle' => null]);

        // Rollback: detalle, total y cuenta corriente siguen como antes.
        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'total' => 5000]);
        $this->assertDatabaseHas('detalle_venta', ['id_venta' => $id, 'id_producto' => 1, 'cantidad' => 2]);
        $this->assertEquals(-5000, $this->movimientoDe($id)->importe);
        $this->assertSame([], $this->stock->avisos());
    }

    public function test_el_detalle_del_error_solo_se_muestra_en_modo_debug(): void
    {
        config(['app.debug' => true]);
        $id = $this->crearVenta();
        Schema::drop('venta_historial');

        $detalle = $this->putJson("/api/ventas/{$id}", $this->cambios())->assertStatus(500)->json('detalle');

        $this->assertStringContainsString('venta_historial', $detalle);
    }

    public function test_si_el_modulo_de_productos_no_responde_devuelve_503(): void
    {
        $this->app->instance(ProductoGateway::class, new class implements ProductoGateway
        {
            public function todos(): array
            {
                throw new IntegracionNoDisponibleException('productos');
            }

            public function buscar(int $id): ?array
            {
                throw new IntegracionNoDisponibleException('productos');
            }
        });
        $id = $this->crearVenta();

        $this->putJson("/api/ventas/{$id}", $this->cambios())
            ->assertStatus(503)
            ->assertJsonPath('codigo', 'INTEGRACION_NO_DISPONIBLE')
            ->assertJsonPath('servicio', 'productos');

        $this->assertVentaSinCambios($id);
    }

    // ------------------------------------------------------------------
    // Log paso a paso (storage/logs/ventas)
    // ------------------------------------------------------------------

    public function test_log_de_modificacion_exitosa(): void
    {
        $id = $this->crearVenta();
        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertOk();

        $log = $this->logVentas();
        $this->assertStringContainsString('.INFO:', $log);
        $this->assertStringContainsString('MODIFICAR VENTA', $log);
        $this->assertStringContainsString("Endpoint ......: PUT /api/ventas/{$id}", $log);
        $this->assertStringContainsString('[PASO 1/7] Buscar y bloquear la venta', $log);
        $this->assertStringContainsString('Total anterior: $ 5.000,00 → total nuevo: $ 5.100,00', $log);
        $this->assertStringContainsString('[PASO 7/7] Avisar al módulo de Stock', $log);
        $this->assertStringContainsString('VENTA MODIFICADA  ·  Respuesta HTTP 200 OK', $log);
    }

    public function test_log_de_modificacion_bloqueada_por_estado(): void
    {
        $id = $this->crearVenta('pagada');
        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertStatus(409);

        $log = $this->logVentas();
        $this->assertStringContainsString('.WARNING:', $log);
        $this->assertStringContainsString('OPERACIÓN RECHAZADA  ·  Respuesta HTTP 409 Conflict', $log);
        $this->assertStringContainsString('Falló en ......: PASO 2 - Verificar el estado', $log);
    }

    public function test_log_de_datos_invalidos(): void
    {
        $id = $this->crearVenta();
        $this->putJson("/api/ventas/{$id}", $this->cambios(['lista' => 'vip']))->assertUnprocessable();

        $log = $this->logVentas();
        $this->assertStringContainsString('.WARNING:', $log);
        $this->assertStringContainsString('MODIFICAR VENTA', $log);
        $this->assertStringContainsString('Falló en ......: PASO 1 - Validación del formato de los datos', $log);
    }

    public function test_log_de_error_indica_el_paso_y_la_linea_de_nuestro_codigo(): void
    {
        $id = $this->crearVenta();
        Schema::drop('venta_historial');
        $this->putJson("/api/ventas/{$id}", $this->cambios())->assertStatus(500);

        $log = $this->logVentas();
        $this->assertStringContainsString('.ERROR:', $log);
        $this->assertStringContainsString('Falló en ......: PASO 6 - Registrar en el historial', $log);
        $this->assertStringContainsString('En nuestro código: app/Services/Ventas/VentaService.php:', $log);
    }
}
