<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\Zona;
use App\Modules\Compras\Models\Compra;
use App\Modules\Compras\Models\DetalleCompra;
use App\Modules\CuentaCorriente\Services\AccountSummary;
use App\Modules\CuentaCorriente\Services\ClientPaymentService;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Login\Models\User;
use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Models\UnidadMedida;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Integraciones\HttpClienteApi;
use App\Modules\Ventas\Integraciones\HttpProductoApi;
use App\Modules\Ventas\Integraciones\HttpStockApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Opt-in explícito: nunca cambia a la BD local habitual ni ejecuta migraciones. */
class CommercialCompletionMySqlTest extends TestCase
{
    private User $admin;

    private Cliente $client;

    private Producto $product;

    private int $supplier;

    private bool $transaction = false;

    private array $initialCounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $database = getenv('ERP_G3_G4_TEST_DATABASE');
        if (! is_string($database) || ! preg_match('/^erp_qa_[a-z0-9_]+$/D', $database)) {
            $this->markTestSkipped('Requiere ERP_G3_G4_TEST_DATABASE=erp_qa_... en MySQL aislado 3315; nunca usa 3306.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 3315, 'database.connections.mysql.database' => $database,
            'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => getenv('ERP_TEST_DB_PASSWORD') ?: '',
            'database.connections.mysql.url' => null, 'ventas.integracion' => 'internal']);
        DB::purge('mysql');
        DB::beginTransaction();
        $this->transaction = true;
        foreach (['g3_stock_requests', 'unidad_medida', 'compra'] as $table) {
            $this->initialCounts[$table] = DB::table($table)->count();
        }
        Http::preventStrayRequests();
        $this->admin = User::factory()->create(['rol' => 'administrativo']);
        $this->client = Cliente::factory()->create(['creado_por' => $this->admin->id]);
        config(['services.internal_api.token' => 'g3-g4-test', 'services.internal_api.user_id' => $this->admin->id]);
        $this->app->bind(ClienteGateway::class, HttpClienteApi::class);
        $this->app->bind(ProductoGateway::class, HttpProductoApi::class);
        $this->app->bind(StockGateway::class, HttpStockApi::class);
        $category = DB::table('categoria')->insertGetId(['nombre' => 'QA-'.Str::uuid(), 'fecha_creacion' => today()], 'id_categoria');
        $brand = DB::table('marca')->insertGetId(['nombre' => 'QA-'.Str::uuid(), 'fecha_creacion' => today()], 'id_marca');
        $this->product = Producto::create(['codigo' => 'QA-'.Str::uuid(), 'nombre' => 'Producto con centavos',
            'descripcion' => 'Prueba aislada', 'precio_unitario' => 10.75, 'stock' => 50, 'stock_minimo' => 5,
            'estado' => 'activo', 'id_categoria' => $category, 'id_marca' => $brand, 'id_usuario_carga' => $this->admin->id]);
        $this->supplier = DB::table('proveedor')->insertGetId(['razon_social' => 'Proveedor QA', 'CUIT' => (string) random_int(20000000000, 30999999999),
            'estado' => 'activo', 'fecha_alta' => today(), 'id_usuario_carga' => $this->admin->id], 'id_proveedor');
        $this->signInAs($this->admin);
    }

    protected function tearDown(): void
    {
        if ($this->transaction) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        parent::tearDown();
    }

    private function unit(?int $product = null, float $factor = 1): UnidadMedida
    {
        return UnidadMedida::create(['id_producto' => $product ?? $this->product->id_producto,
            'nombre_unidad' => 'Unidad '.Str::random(5), 'equivalencia_base' => $factor, 'es_base' => 1]);
    }

    private function sale(array $overrides = [])
    {
        return $this->postJson('/api/ventas', array_replace(['request_key' => (string) Str::uuid(), 'cliente_id' => $this->client->id, 'lista' => 'minorista',
            'fecha_vencimiento' => today()->addDays(30)->toDateString(),
            'items' => [['id' => $this->product->id_producto, 'cantidad' => 2, 'descuento' => 0]]], $overrides));
    }

    private function purchase(): Compra
    {
        $unit = $this->unit();
        $purchase = Compra::create(['numero_compra' => 'QA-'.Str::random(12), 'id_proveedor' => $this->supplier,
            'importe_total' => 107.50, 'saldo_pendiente' => 107.50, 'estado' => 'pendiente',
            'fecha_compra' => today(), 'id_usuario' => $this->admin->id]);
        DetalleCompra::create(['id_compra' => $purchase->id_compra, 'id_producto' => $this->product->id_producto,
            'id_unidad' => $unit->id_unidad, 'cantidad' => 10, 'cantidad_recibida' => 0, 'precio_unitario' => 10.75, 'subtotal' => 107.50]);

        return $purchase;
    }

    private function confirmed(array $overrides = []): int
    {
        $id = $this->sale($overrides)->assertCreated()->json('venta_id');
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'confirmada'])->assertOk();

        return $id;
    }

    private function returned(int $sale, int $qty = 1, string $condition = 'apto', ?string $key = null)
    {
        $detail = DB::table('detalle_venta')->where('id_venta', $sale)->value('id_detalle');

        return $this->postJson('/api/ventas/'.$sale.'/devoluciones', ['request_key' => $key ?? (string) Str::uuid(),
            'motivo' => 'Mercadería recibida físicamente', 'items' => [['detalle_venta_id' => $detail, 'cantidad' => $qty, 'condicion' => $condition]]]);
    }

    public function test_receipt_contract_preserves_line_unit_and_base_equivalence(): void
    {
        $purchase = $this->purchase();
        $box = $this->unit(null, 12);
        $detail = DetalleCompra::create(['id_compra' => $purchase->id_compra, 'id_producto' => $this->product->id_producto,
            'id_unidad' => $box->id_unidad, 'cantidad' => 2, 'cantidad_recibida' => 0, 'precio_unitario' => 120, 'subtotal' => 240]);
        $rows = $this->getJson('/api/compras/'.$purchase->id_compra)->assertOk()->json('data.detalles');
        $row = collect($rows)->firstWhere('id_detalle_compra', $detail->id_detalle_compra);
        $this->assertEquals(12, $row['equivalencia_base']);
        $this->assertSame($box->id_unidad, $row['id_unidad']);
        $this->postJson('/api/compras/'.$purchase->id_compra.'/recepciones', ['items' => [[
            'id_detalle_compra' => $detail->id_detalle_compra, 'id_producto' => $this->product->id_producto,
            'id_unidad' => $box->id_unidad, 'cantidad_recibida' => 1]]])->assertCreated();
        $this->assertSame(62, (int) $this->product->fresh()->stock);
        $this->assertEquals(10, DB::table('producto_proveedor')->where('id_producto', $this->product->id_producto)->value('precio_acordado'));
    }

    public function test_sent_order_is_not_editable_and_can_only_be_cancelled_without_purchases(): void
    {
        $body = ['id_proveedor' => $this->supplier, 'items' => [['id_producto' => $this->product->id_producto,
            'cantidad_solicitada' => 2, 'precio_estimado' => 10.75]]];
        $id = $this->postJson('/api/ordenes-compra', $body)->assertCreated()->json('data.id_orden');
        $this->putJson('/api/ordenes-compra/'.$id, $body)->assertOk();
        $this->patchJson('/api/ordenes-compra/'.$id.'/enviar')->assertOk();
        $this->patchJson('/api/ordenes-compra/'.$id.'/cancelar')->assertOk();
        $this->putJson('/api/ordenes-compra/'.$id, $body)->assertStatus(400);
        $this->assertDatabaseHas('orden_compra', ['id_orden' => $id, 'estado' => 'cancelada']);
        $this->assertDatabaseHas('log_auditoria', ['tabla_afectada' => 'orden_compra', 'id_registro' => $id]);
    }

    public function test_latest_g3_cost_and_provider_increase_updates_prices_and_history(): void
    {
        $this->product->update(['precio_unitario' => 150, 'costo' => 100, 'recargo' => 50]);
        DB::table('producto_proveedor')->insert(['id_producto' => $this->product->id_producto, 'id_proveedor' => $this->supplier,
            'es_proveedor_principal' => 1, 'precio_acordado' => 100, 'activo' => 1, 'fecha_asociacion' => today(), 'id_usuario' => $this->admin->id]);
        $this->postJson('/api/productos/aumento-masivo', ['porcentaje' => 10, 'base_aumento' => 'costo', 'id_proveedor' => $this->supplier])->assertOk();
        $this->assertEquals(110, $this->product->fresh()->costo);
        $this->assertEquals(165, $this->product->fresh()->precio_unitario);
        $this->postJson('/api/productos/aumento-masivo', ['porcentaje' => 10, 'base_aumento' => 'precio', 'ids' => [$this->product->id_producto]])->assertOk();
        $this->assertEquals(181.5, $this->product->fresh()->precio_unitario);
        $this->assertDatabaseHas('HISTORIAL_PRECIO', ['id_producto' => $this->product->id_producto, 'precio' => 165]);
    }

    public function test_credit_limit_blocks_confirmation_audits_attempt_and_real_payment_releases_it(): void
    {
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => 10])->assertOk();
        $id = $this->sale()->assertCreated()->json('venta_id');
        $this->getJson('/api/ventas/clientes/'.$this->client->id.'/credito')->assertOk()->assertJsonPath('data.excedido', true);
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'confirmada'])->assertUnprocessable();
        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('g4_credit_events', ['cliente_id' => $this->client->id, 'evento' => 'confirmacion_bloqueada']);
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        app(ClientPaymentService::class)->register($this->client->id, ['request_key' => (string) Str::uuid(), 'monto' => 11.50, 'medio_pago' => 'efectivo'], $this->admin);
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'confirmada'])->assertOk();
        $this->assertDatabaseHas('g4_credit_events', ['cliente_id' => $this->client->id, 'evento' => 'limite_modificado']);
    }

    public function test_unlimited_clients_keep_behaviour_and_zero_limit_rejects_owed_sale(): void
    {
        $this->getJson('/api/ventas/clientes/'.$this->client->id.'/credito')->assertOk()->assertJsonPath('data.limite_credito', null);
        $this->confirmed();
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => 0])->assertOk();
        $id = $this->sale()->assertCreated()->json('venta_id');
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'preparada'])->assertUnprocessable();
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => -1])->assertUnprocessable();
    }

    public function test_uncredited_cheque_does_not_release_credit_limit(): void
    {
        $id = $this->sale()->assertCreated()->json('venta_id');
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        app(ClientPaymentService::class)->register($this->client->id, ['request_key' => (string) Str::uuid(), 'monto' => 21.50, 'medio_pago' => 'cheque',
            'datos_medio_pago' => ['numero_cheque' => 'QA-CREDITO', 'banco_emisor' => 'Banco QA', 'fecha_cobro' => today()->toDateString()]], $this->admin);
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => 10])->assertOk();
        $this->getJson('/api/ventas/clientes/'.$this->client->id.'/credito')->assertOk()->assertJsonPath('data.exposicion', 21.5);
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'confirmada'])->assertUnprocessable();
    }

    public function test_return_uses_original_net_price_is_idempotent_and_rejects_over_return(): void
    {
        $id = $this->confirmed(['descuento_global' => ['modo' => 'monto', 'valor' => 2.50]]);
        $key = (string) Str::uuid();
        $first = $this->returned($id, 1, 'apto', $key)->assertCreated()->assertJsonPath('data.nota_credito.total', '9.50')->json('data.devolucion_id');
        $this->returned($id, 1, 'apto', $key)->assertOk()->assertJsonPath('data.devolucion_id', $first);
        $this->assertSame(49, (int) $this->product->fresh()->stock);
        $this->assertEquals(9.5, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
        $this->returned($id, 2)->assertUnprocessable();
        $this->returned($id, 1)->assertCreated();
        $this->assertSame(50, (int) $this->product->fresh()->stock);
        $this->assertEquals(19, DB::table('g4_notas_credito')->where('venta_id', $id)->sum('total'));
        $this->assertEquals(0, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
        $this->deleteJson('/api/ventas/'.$id, ['motivo' => 'No borrar una venta con devolución'])->assertConflict();
    }

    public function test_paid_sale_return_creates_real_credit_balance_and_pdf(): void
    {
        $id = $this->confirmed();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        app(ClientPaymentService::class)->register($this->client->id, ['request_key' => (string) Str::uuid(), 'monto' => 21.50, 'medio_pago' => 'efectivo'], $this->admin);
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'pagada'])->assertOk();
        $return = $this->returned($id)->assertCreated()->json('data.devolucion_id');
        $account = app(FinancialReadService::class)->account($this->client->id);
        $this->assertEquals(10.75, $account['balance']['saldo_a_favor']);
        $pdf = $this->get('/devoluciones/'.$return.'/nota')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->getJson('/api/ventas/devoluciones/'.$return)->assertOk()->assertJsonPath('data.nota_credito.numero', 'NC-DEV-'.$return);
    }

    public function test_damaged_return_is_recorded_and_credited_without_increasing_available_stock(): void
    {
        $id = $this->confirmed();
        $return = $this->returned($id, 1, 'danado')->assertCreated()->json('data.devolucion_id');
        $this->assertSame(48, (int) $this->product->fresh()->stock);
        $this->assertDatabaseHas('g4_devolucion_detalles', ['devolucion_id' => $return, 'condicion' => 'danado', 'cantidad' => 1]);
        $this->assertEquals(10.75, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
    }

    public function test_return_credit_is_assigned_to_its_sale_and_rounding_sums_to_original_total(): void
    {
        $first = $this->confirmed();
        $second = $this->confirmed(['items' => [['id' => $this->product->id_producto, 'cantidad' => 3, 'descuento' => 0]],
            'descuento_global' => ['modo' => 'monto', 'valor' => 0.01]]);
        foreach (range(1, 3) as $i) {
            $this->returned($second)->assertCreated();
        }
        $this->assertEquals(32.24, DB::table('g4_notas_credito')->where('venta_id', $second)->sum('total'));
        $pending = app(FinancialReadService::class)->account($this->client->id)['invoices'];
        $this->assertCount(1, $pending);
        $this->assertSame($first, $pending->first()['id']);
    }

    public function test_invalid_return_and_another_actor_cannot_generate_credit(): void
    {
        $id = $this->sale()->assertCreated()->json('venta_id');
        $this->returned($id)->assertConflict();
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'confirmada'])->assertOk();
        $this->postJson('/api/ventas/'.$id.'/devoluciones', ['request_key' => (string) Str::uuid(), 'motivo' => 'Detalle ajeno',
            'items' => [['detalle_venta_id' => 2147483647, 'cantidad' => 1, 'condicion' => 'apto']]])->assertUnprocessable();
        $this->signInAs(User::factory()->create(['rol' => 'repartidor']));
        $this->returned($id)->assertForbidden();
        $this->assertEquals(0, DB::table('g4_devoluciones')->where('venta_id', $id)->count());
    }

    public function test_model_relations_use_real_foreign_keys_and_real_collection_model(): void
    {
        $id = $this->confirmed();
        $this->assertTrue($this->client->ventas()->whereKey($id)->exists());
        $this->assertTrue($this->admin->ventas()->whereKey($id)->exists());
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $result = app(ClientPaymentService::class)->register($this->client->id, ['request_key' => (string) Str::uuid(), 'monto' => 10, 'medio_pago' => 'efectivo'], $this->admin);
        $this->assertTrue($this->client->cobros()->whereKey($result['id_cobro'])->exists());
        $this->assertTrue($this->admin->cobros()->whereKey($result['id_cobro'])->exists());
    }

    public function test_accountant_can_follow_debtor_detail_but_cannot_write_or_return_stock(): void
    {
        $id = $this->confirmed();
        config(['services.balances.transport' => 'internal', 'services.billing.transport' => 'internal']);
        $this->signInAs(User::factory()->create(['rol' => 'contador']));
        $this->get('/clientes/'.$this->client->id)->assertOk()->assertDontSee('Registrar pago de cuenta');
        $this->get('/clientes/'.$this->client->id.'/editar')->assertRedirect('/dashboard');
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => 0])->assertForbidden();
        $this->returned($id)->assertForbidden();
        $this->get('/devoluciones')->assertOk();
    }

    public function test_inactivity_is_capped_at_thirty_minutes_even_with_old_environment_value(): void
    {
        config(['session.lifetime' => 120]);
        $this->travel(29)->minutes();
        $this->get('/devoluciones')->assertOk();
        $this->travel(31)->minutes();
        $this->get('/devoluciones')->assertRedirect('/login?reason=session_expired');
    }

    public function test_comments_can_be_changed_and_removed_after_payment_without_altering_financial_data(): void
    {
        $id = $this->confirmed();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        app(ClientPaymentService::class)->register($this->client->id, ['request_key' => (string) Str::uuid(), 'monto' => 21.5, 'medio_pago' => 'efectivo'], $this->admin);
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'pagada'])->assertOk();
        $before = [$this->product->fresh()->stock, DB::table('cuenta_corriente_movimientos')->count()];
        $this->patchJson('/api/ventas/'.$id.'/observaciones', ['observaciones' => 'Revisado por administración'])->assertOk();
        $this->patchJson('/api/ventas/'.$id.'/observaciones', ['observaciones' => ''])->assertOk();
        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'estado' => 'pagada', 'total' => 21.5, 'observaciones' => '']);
        $this->assertSame($before, [$this->product->fresh()->stock, DB::table('cuenta_corriente_movimientos')->count()]);
        $this->assertEquals(2, DB::table('venta_historial')->where('id_venta', $id)->where('accion', 'observaciones')->count());
        $this->patchJson('/api/ventas/'.$id.'/observaciones', ['observaciones' => str_repeat('x', 151)])->assertUnprocessable();
    }

    public function test_summary_includes_opening_balance_and_only_real_payments_and_reversals(): void
    {
        $entries = [
            ['fecha' => '2026-10-01', 'monto' => -100, 'tipo' => 'venta'],
            ['fecha' => '2026-11-01', 'monto' => 30, 'tipo' => 'pago'],
            ['fecha' => '2026-11-02', 'monto' => 10, 'tipo' => 'nota_credito'],
            ['fecha' => '2026-11-03', 'monto' => -5, 'tipo' => 'ajuste', 'referencia_externa' => 'reversion_pago:1'],
            ['fecha' => '2026-11-10', 'monto' => 20, 'tipo' => 'pago'],
        ];
        $summary = AccountSummary::calculate($entries, ['modoFecha' => 'periodo', 'fechaDesde' => '2026-11-01', 'fechaHasta' => '2026-11-03']);
        $this->assertEquals(['pagos_netos' => 25, 'deuda_al_corte' => 65, 'saldo_a_favor_al_corte' => 0, 'deuda_actual' => 45], $summary);
    }

    public function test_credit_does_not_treat_legacy_unverified_positive_entry_as_available_money(): void
    {
        $id = $this->sale()->assertCreated()->json('venta_id');
        DB::table('cuenta_corriente_movimientos')->insert(['cliente_id' => $this->client->id, 'tipo' => 'pago',
            'descripcion' => 'Histórico sin aplicación', 'importe' => 21.50, 'created_at' => now(), 'updated_at' => now()]);
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => 0])->assertOk();
        $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'confirmada'])->assertUnprocessable();
        $this->getJson('/api/ventas/clientes/'.$this->client->id.'/credito')->assertOk()->assertJsonPath('data.pagos_sin_respaldo', 21.5);
    }

    public function test_order_progress_aggregates_all_linked_purchases_not_only_the_last_receipt(): void
    {
        $order = $this->postJson('/api/ordenes-compra', ['id_proveedor' => $this->supplier, 'items' => [
            ['id_producto' => $this->product->id_producto, 'cantidad_solicitada' => 4, 'precio_estimado' => 10.75]]])->assertCreated()->json('data.id_orden');
        $this->patchJson('/api/ordenes-compra/'.$order.'/enviar')->assertOk();
        $body = ['id_proveedor' => $this->supplier, 'id_orden' => $order, 'items' => [
            ['id_producto' => $this->product->id_producto, 'cantidad' => 2, 'precio_unitario' => 10.75]]];
        $first = $this->postJson('/api/compras', $body)->assertCreated()->json('data.id_compra');
        $second = $this->postJson('/api/compras', $body)->assertCreated()->json('data.id_compra');
        $this->postJson('/api/compras', $body)->assertUnprocessable();
        $receipt = ['items' => [['id_producto' => $this->product->id_producto, 'cantidad' => 2]]];
        $this->postJson('/api/compras/'.$first.'/recepciones', $receipt)->assertCreated();
        $this->assertDatabaseHas('orden_compra', ['id_orden' => $order, 'estado' => 'recibida_parcialmente']);
        $this->postJson('/api/compras/'.$second.'/recepciones', $receipt)->assertCreated();
        $this->assertDatabaseHas('orden_compra', ['id_orden' => $order, 'estado' => 'completada']);
    }

    public function test_delivery_consumer_selects_confirmed_sales_and_excludes_pending_orders(): void
    {
        config(['g2.transport' => 'internal', 'g2.ventas_token' => 'g3-g4-test']);
        $zone = Zona::create(['nombre' => 'Zona QA']);
        $this->client->update(['zona_id' => $zone->id]);
        $pending = $this->sale()->assertCreated()->json('venta_id');
        $confirmed = $this->confirmed();
        $sales = app(VentasApiClient::class)->obtenerVentasPendientesDespacho($zone->id);
        $ids = array_column($sales, 'id_venta');
        $this->assertContains($confirmed, $ids);
        $this->assertNotContains($pending, $ids);
    }

    public function test_operational_views_render_and_expose_the_new_controls(): void
    {
        $checks = ['/devoluciones' => 'Recibir una devolución', '/historial-ventas' => 'observaciones',
            '/ventas' => 'creditoCliente', '/saldo' => 'erpAccountSummary', '/inventario' => 'Inventario', '/compras' => 'id_detalle_compra'];
        foreach ($checks as $url => $text) {
            $response = $this->get($url)->assertOk();
            $this->assertStringContainsString($text, $response->getContent(), $url);
            if ($directory = getenv('ERP_QA_RENDER_DIR')) {
                if (! is_dir($directory)) {
                    mkdir($directory, 0700, true);
                }
                file_put_contents($directory.'/'.trim($url, '/').'.html', $response->getContent());
            }
        }
    }

    public function test_credit_cannot_be_bypassed_by_increasing_or_restoring_a_confirmed_sale(): void
    {
        $id = $this->confirmed();
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => 21.50])->assertOk();
        $stock = $this->product->fresh()->stock;
        $this->putJson('/api/ventas/'.$id, ['lista' => 'minorista', 'items' => [
            ['id' => $this->product->id_producto, 'cantidad' => 3, 'descuento' => 0]]])->assertUnprocessable();
        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'total' => 21.5]);
        $this->assertEquals($stock, $this->product->fresh()->stock);
        $this->deleteJson('/api/ventas/'.$id, ['motivo' => 'Prueba de crédito'])->assertOk();
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => 0])->assertOk();
        $this->postJson('/api/ventas/'.$id.'/restaurar')->assertUnprocessable();
        $this->assertDatabaseHas('venta', ['id_venta' => $id, 'estado' => 'cancelada']);
        $this->assertEquals(2, DB::table('g4_credit_events')->where('venta_id', $id)->where('evento', 'confirmacion_bloqueada')->count());
    }

    public function test_genuine_credit_balance_extends_available_credit(): void
    {
        $sale = $this->confirmed();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        app(ClientPaymentService::class)->register($this->client->id, ['request_key' => (string) Str::uuid(),
            'monto' => 21.5, 'medio_pago' => 'efectivo'], $this->admin);
        $this->returned($sale)->assertCreated();
        $this->putJson('/api/ventas/clientes/'.$this->client->id.'/credito', ['limite_credito' => 0])->assertOk()
            ->assertJsonPath('data.credito_disponible', 10.75)->assertJsonPath('data.saldo_a_favor_aplicable', 10.75);
    }
}
