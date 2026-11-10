<?php

namespace Tests\Feature;

use App\Modules\Clientes\Models\Cliente;
use App\Modules\CuentaCorriente\Services\CreditNoteEvidence;
use App\Modules\Login\Models\User;
use App\Modules\Productos\Models\Producto;
use App\Modules\Proveedores\Models\ProductoProveedor;
use App\Modules\Stock\Models\UnidadMedida;
use App\Modules\Stock\Services\StockService;
use App\Modules\Ventas\Services\CreditLimitService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\UsesG3Database;

class FinalClosureMySqlTest extends TestCase
{
    use UsesG3Database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDatabaseTransaction();
    }

    public function test_both_unit_detail_aliases_respect_product_ownership(): void
    {
        $product = Producto::where('codigo', 'QA-FIXTURE-BASE')->firstOrFail();
        $unit = UnidadMedida::where('id_producto', $product->id_producto)->firstOrFail();
        foreach (['/api', '/api/v1'] as $prefix) {
            $this->getJson($prefix.'/productos/'.$product->id_producto.'/unidades/'.$unit->id_unidad)
                ->assertOk()->assertJsonPath('data.id_producto', $product->id_producto)->assertJsonPath('data.id_unidad', $unit->id_unidad);
            $this->getJson($prefix.'/productos/'.($product->id_producto + 9999).'/unidades/'.$unit->id_unidad)->assertNotFound();
            $this->getJson($prefix.'/productos/'.$product->id_producto.'/unidades/abc')->assertNotFound();
        }
    }

    public function test_one_cent_cost_change_updates_price_once(): void
    {
        $product = Producto::where('codigo', 'QA-FIXTURE-BASE')->firstOrFail();
        $product->update(['costo' => 100.01, 'recargo' => 0, 'precio_unitario' => 100.01]);
        ProductoProveedor::create(['id_producto' => $product->id_producto, 'id_proveedor' => DB::table('proveedor')->value('id_proveedor'),
            'activo' => true, 'es_proveedor_principal' => true, 'precio_acordado' => 100.02, 'id_usuario' => auth()->id()]);
        $product->sincronizarCostoReposicion(auth()->id());
        $this->assertEquals(100.02, $product->fresh()->precio_unitario);
        $this->assertDatabaseCount('historial_precio', 1);
        $product->sincronizarCostoReposicion(auth()->id());
        $this->assertDatabaseCount('historial_precio', 1);
    }

    public function test_edit_below_paid_amount_is_422_and_keeps_purchase_and_payment(): void
    {
        $body = $this->purchase();
        $id = $this->postJson('/api/compras', $body)->assertCreated()->json('data.id_compra');
        $this->postJson('/api/compras/'.$id.'/pagos', ['monto' => 70, 'metodo_pago' => 'transferencia'])->assertCreated();
        $body['items'][0]['precio_unitario'] = 50;
        $this->putJson('/api/compras/'.$id, $body)->assertUnprocessable();
        $this->assertDatabaseHas('compra', ['id_compra' => $id, 'importe_total' => 100]);
        $this->assertEquals(70, DB::table('pago_proveedor')->where('id_compra', $id)->sum('importe'));
    }

    public function test_unverified_credit_note_never_frees_credit_limit_even_with_sale_reference(): void
    {
        $client = Cliente::factory()->create(['creado_por' => auth()->id(), 'limite_credito' => 100]);
        DB::table('cuenta_corriente_movimientos')->insert(['cliente_id' => $client->id, 'venta_id' => null,
            'tipo' => 'venta', 'importe' => -150, 'descripcion' => 'Cargo histórico', 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('cuenta_corriente_movimientos')->insertGetId(['cliente_id' => $client->id, 'venta_id' => null,
            'tipo' => 'nota_credito', 'importe' => 60, 'descripcion' => 'Sin documento canónico', 'created_at' => now(), 'updated_at' => now()]);
        $entry = DB::table('cuenta_corriente_movimientos')->find($id);
        $this->assertFalse(app(CreditNoteEvidence::class)->verified($entry));
        $status = app(CreditLimitService::class)->status($client->id);
        $this->assertEquals(150, $status['exposicion']);
        $this->assertEquals(60, $status['notas_credito_sin_respaldo']);
        $this->assertTrue($status['excedido']);
        $entry->venta_id = 123;
        $this->assertFalse(app(CreditNoteEvidence::class)->verified($entry));
    }

    public function test_cancelling_receipt_with_lot_reverses_only_that_lot_and_keeps_history(): void
    {
        $body = $this->purchase();
        $body['recibir_ahora'] = true;
        $body['items'][0] += ['nro_lote' => 'CANCEL-LOTE', 'fecha_vencimiento' => today()->addDays(30)->toDateString()];
        $id = $this->postJson('/api/compras', $body)->assertCreated()->json('data.id_compra');
        $product = Producto::where('codigo', 'QA-FIXTURE-BASE')->firstOrFail();
        $this->assertSame(1001, (int) $product->stock);
        $this->patchJson('/api/compras/'.$id.'/cancelar')->assertOk();
        $this->assertSame(1000, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('lote', ['nro_lote' => 'CANCEL-LOTE', 'cantidad_actual' => 0]);
        $this->assertDatabaseCount('recepcion', 1);
        $this->patchJson('/api/compras/'.$id.'/cancelar')->assertStatus(409);
        $this->assertSame(1000, (int) $product->fresh()->stock);
    }

    public function test_cancel_cannot_borrow_other_stock_when_received_lot_has_been_sold(): void
    {
        $body = $this->purchase();
        $body['recibir_ahora'] = true;
        $body['items'][0] += ['nro_lote' => 'VENDIDO-LOTE', 'fecha_vencimiento' => today()->addDays(30)->toDateString()];
        $id = $this->postJson('/api/compras', $body)->assertCreated()->json('data.id_compra');
        $product = Producto::where('codigo', 'QA-FIXTURE-BASE')->firstOrFail();
        app(StockService::class)->registrarMovimiento($product, 'venta', 1, 'Consumo previo', auth()->id());
        $this->patchJson('/api/compras/'.$id.'/cancelar')->assertUnprocessable();
        $this->assertDatabaseHas('compra', ['id_compra' => $id, 'estado' => 'completada']);
        $this->assertSame(1000, (int) $product->fresh()->stock);
    }

    private function purchase(): array
    {
        return ['id_proveedor' => DB::table('proveedor')->value('id_proveedor'),
            'items' => [['id_producto' => Producto::where('codigo', 'QA-FIXTURE-BASE')->value('id_producto'), 'cantidad' => 1, 'precio_unitario' => 100]]];
    }
    public function test_expired_lot_is_not_available_through_either_stock_alias(): void
    {
        $product = Producto::where('codigo', 'QA-FIXTURE-BASE')->firstOrFail();
        app(\App\Modules\Stock\Services\LotService::class)->register(['id_producto' => $product->id_producto, 'nro_lote' => 'VENCIDO-QA',
            'fecha_vencimiento' => today()->subDay()->toDateString(), 'cantidad' => 1000, 'asociar_ingreso' => false], auth()->id());
        $this->getJson('/api/stock/'.$product->id_producto)->assertOk()->assertJsonPath('stock', 1000)->assertJsonPath('disponible', 0)->assertJsonPath('data.stock_disponible', 0);
        foreach (['/api', '/api/v1'] as $prefix) {
            $this->getJson($prefix.'/stock/disponibilidad?id_producto='.$product->id_producto)->assertOk()->assertJsonPath('data.0.stock_disponible', 0);
        }
    }
    public function test_historical_cash_closures_show_names_including_inactive_owner(): void
    {
        $owner = User::factory()->create(['nombre' => 'Operador histórico', 'estado' => 'inactivo']);
        DB::table('CAJA')->insert(['id_usuario' => $owner->id, 'fecha' => today(), 'estado' => 'cerrada', 'monto_inicial' => 10,
            'monto_final' => 10, 'id_usuario_cierre' => auth()->id(), 'fecha_hora_apertura' => now(), 'fecha_hora_cierre' => now()]);
        $rows = app(\App\Modules\Caja\Services\CajaService::class)->obtenerHistorialCierres();
        $this->assertSame('Operador histórico', $rows[0]['nombre_usuario']);
        $this->assertSame(auth()->user()->nombre, $rows[0]['nombre_usuario_cierre']);
        $this->assertArrayNotHasKey('password_hash', $rows[0]);
    }
    public function test_order_partial_closure_requires_explicit_confirmation_and_physical_receipt(): void
    {
        $body = $this->purchase();
        $order = $this->postJson('/api/ordenes-compra', ['id_proveedor' => $body['id_proveedor'],
            'items' => [['id_producto' => $body['items'][0]['id_producto'], 'cantidad_solicitada' => 5, 'precio_estimado' => 100]]])->assertCreated()->json('data.id_orden');
        $body['id_orden'] = $order; $body['recibir_ahora'] = true;
        $body['items'][0]['cantidad'] = 5; $body['items'][0]['cantidad_recibida'] = 2;
        $id = $this->postJson('/api/compras', $body)->assertCreated()->json('data.id_compra');
        $this->patchJson('/api/ordenes-compra/'.$order.'/cambiar-estado', ['estado' => 'completada'])->assertUnprocessable();
        $this->patchJson('/api/ordenes-compra/'.$order.'/cambiar-estado', ['estado' => 'completada', 'confirmar_cierre_parcial' => true])->assertOk();
        $this->assertDatabaseHas('compra', ['id_compra' => $id, 'importe_total' => 200, 'cierre_parcial' => 1]);
        $this->assertDatabaseHas('orden_compra', ['id_orden' => $order, 'estado' => 'completada', 'cierre_parcial' => 1]);
    }
}
