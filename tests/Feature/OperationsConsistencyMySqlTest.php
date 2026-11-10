<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\Zona;
use App\Modules\Compras\Models\Compra;
use App\Modules\Compras\Models\DetalleCompra;
use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\ConciliacionBancaria\Services\BankPeriodService;
use App\Modules\ConciliacionBancaria\Services\BankReconciliationService;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
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
class OperationsConsistencyMySqlTest extends TestCase
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

    public function test_ingress_error_rolls_back_stock_movements_and_supplier_associations(): void
    {
        $inactive = $this->product->replicate();
        $inactive->codigo = 'QA-'.Str::uuid();
        $inactive->estado = 'inactivo';
        $inactive->save();
        $counts = [DB::table('movimiento_stock')->count(), DB::table('producto_proveedor')->count()];
        $this->postJson('/api/stock/ingresos', ['id_proveedor' => $this->supplier, 'items' => [
            ['id_producto' => $this->product->id_producto, 'cantidad' => 3],
            ['id_producto' => $inactive->id_producto, 'cantidad' => 1],
        ]])->assertStatus(400);
        $this->assertSame(50, (int) $this->product->fresh()->stock);
        $this->assertSame($counts, [DB::table('movimiento_stock')->count(), DB::table('producto_proveedor')->count()]);
    }

    public function test_ingress_valid_batch_commits_every_item_and_unit_error_commits_none(): void
    {
        $unit = $this->unit(null, 12);
        $url = '/api/stock/ingresos';
        $items = [['id_producto' => $this->product->id_producto, 'cantidad' => 2, 'id_unidad' => $unit->id_unidad],
            ['id_producto' => $this->product->id_producto, 'cantidad' => 3]];
        $this->postJson($url, ['items' => $items])->assertCreated();
        $this->assertSame(77, (int) $this->product->fresh()->stock);
        $other = $this->product->replicate();
        $other->codigo = 'QA-'.Str::uuid();
        $other->save();
        $foreign = $this->unit($other->id_producto);
        $items[1]['id_unidad'] = $foreign->id_unidad;
        $this->postJson($url, ['items' => $items])->assertStatus(400);
        $this->assertSame(77, (int) $this->product->fresh()->stock);
    }

    public function test_repeated_sale_returns_same_sale_debt_and_stock_and_conflicting_body_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $before = [DB::table('venta')->count(), DB::table('cuenta_corriente_movimientos')->count(), DB::table('g4_stock_operations')->count()];
        $id = $this->sale(['request_key' => $key])->assertCreated()->json('venta_id');
        $this->sale(['request_key' => $key])->assertOk()->assertJsonPath('venta_id', $id)->assertJsonPath('repetido', true);
        $this->assertSame(48, (int) $this->product->fresh()->stock);
        $this->assertSame(array_map(fn ($v) => $v + 1, $before), [DB::table('venta')->count(), DB::table('cuenta_corriente_movimientos')->count(), DB::table('g4_stock_operations')->count()]);
        $this->sale(['request_key' => $key, 'observaciones' => 'Otro pedido'])->assertConflict();
        $this->assertSame(48, (int) $this->product->fresh()->stock);
    }

    public function test_sale_header_key_works_and_failed_sale_releases_reservation(): void
    {
        $key = (string) Str::uuid();
        $this->sale(['request_key' => $key, 'items' => [['id' => $this->product->id_producto, 'cantidad' => 51, 'descuento' => 0]]])->assertStatus(503);
        $this->assertDatabaseMissing('g4_sale_requests', ['request_key' => $key]);
        $this->sale(['request_key' => $key])->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->sale(['request_key' => $key])->assertOk();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->sale(['request_key' => $key])->assertUnprocessable();
    }

    public function test_committed_sale_replay_does_not_depend_on_product_api_being_available(): void
    {
        $key = (string) Str::uuid();
        $id = $this->sale(['request_key' => $key])->assertCreated()->json('venta_id');
        $broken = $this->createMock(ProductoGateway::class);
        $broken->expects($this->never())->method('todos');
        $this->app->instance(ProductoGateway::class, $broken);
        $this->sale(['request_key' => $key])->assertOk()->assertJsonPath('venta_id', $id);
    }

    public function test_another_actor_cannot_reuse_sale_reference_and_unknown_products_do_not_reserve_it(): void
    {
        $key = (string) Str::uuid();
        $this->sale(['request_key' => $key])->assertCreated();
        $this->signInAs(User::factory()->create(['rol' => 'administrativo']));
        $this->sale(['request_key' => $key])->assertConflict();
        $newKey = (string) Str::uuid();
        $this->sale(['request_key' => $newKey, 'items' => [['id' => 2147483647, 'cantidad' => 1, 'descuento' => 0]]])->assertUnprocessable();
        $this->assertDatabaseMissing('g4_sale_requests', ['request_key' => $newKey]);
    }

    public function test_purchase_batch_records_all_methods_and_rolls_back_entire_overpayment(): void
    {
        $purchase = $this->purchase();
        $url = '/api/compras/'.$purchase->id_compra.'/pagos';
        $before = DB::table('pago_proveedor')->count();
        $this->postJson($url, ['pagos' => [['metodo_pago' => 'efectivo', 'importe' => 70], ['metodo_pago' => 'transferencia', 'importe' => 40]]])->assertStatus(400);
        $this->assertDatabaseCount('PAGO_PROVEEDOR', $before);
        $this->postJson($url, ['pagos' => [['metodo_pago' => 'efectivo', 'monto' => 70], ['metodo_pago' => 'transferencia', 'importe' => 37.50]]])
            ->assertCreated()->assertJsonCount(2, 'data.pagos')->assertJsonPath('data.resumen.saldo_a_pagar', 0);
        $this->assertEquals(107.50, DB::table('pago_proveedor')->where('id_compra', $purchase->id_compra)->sum('importe'));
        $this->postJson($url, ['pagos' => [['metodo_pago' => 'efectivo', 'importe' => 1]], 'importe' => 1])->assertStatus(400);
    }

    private function delivery(): array
    {
        config(['g2.transport' => 'internal']);
        $zone = Zona::create(['nombre' => 'Zona QA']);
        $this->client->update(['zona_id' => $zone->id]);
        $first = $this->sale()->assertCreated()->json('venta_id');
        $second = $this->sale()->assertCreated()->json('venta_id');
        foreach ([$first, $second] as $id) {
            $this->patchJson('/api/ventas/'.$id.'/estado', ['estado' => 'confirmada'])->assertOk();
        }
        $driver = User::factory()->create(['rol' => 'repartidor']);
        $delivery = $this->postJson('/g2/api/entregas', ['repartidor_id' => $driver->id, 'pedidos_ids' => [$first, $second], 'fecha_salida' => today()->toDateString()])
            ->assertCreated()->json('data.id_entrega');

        return [$delivery, $first, $second, $driver];
    }

    public function test_assigned_sale_is_immutable_until_removed_from_preparation(): void
    {
        [$delivery, $first, $second] = $this->delivery();
        $uri = '/api/ventas/'.$second;
        $this->putJson($uri, ['lista' => 'minorista', 'items' => [['id' => $this->product->id_producto, 'cantidad' => 3, 'descuento' => 0]]])->assertConflict();
        $this->deleteJson($uri, ['motivo' => 'Cambio del pedido'])->assertConflict();
        $this->getJson($uri)->assertOk()->assertJsonPath('data.acciones.puede_modificar', false);
        $history = $this->get('/historial-ventas')->assertOk()->viewData('ventas')->firstWhere('id', $second);
        $this->assertFalse($history['puede_modificar']);
        $this->assertFalse($history['puede_eliminar']);
        $this->putJson('/g2/api/entregas/'.$delivery.'/pedidos', ['pedidos_ids' => [$first]])->assertOk();
        $this->putJson($uri, ['lista' => 'minorista', 'items' => [['id' => $this->product->id_producto, 'cantidad' => 3, 'descuento' => 0]]])->assertOk();
        $this->assertDatabaseHas('remito', ['id_entrega' => $delivery, 'id_venta' => $second, 'estado' => 'anulado']);
    }

    public function test_sale_cannot_be_cancelled_or_edited_when_delivery_is_on_the_road(): void
    {
        [$delivery, $first, $second] = $this->delivery();
        $this->patchJson('/g2/api/entregas/'.$delivery.'/pedidos/'.$first.'/entregado')->assertOk();
        $before = (int) $this->product->fresh()->stock;
        $this->putJson('/api/ventas/'.$second, ['lista' => 'minorista', 'items' => [['id' => $this->product->id_producto, 'cantidad' => 3, 'descuento' => 0]]])->assertConflict();
        $this->deleteJson('/api/ventas/'.$second, ['motivo' => 'Anulación indebida'])->assertConflict();
        $this->assertSame($before, (int) $this->product->fresh()->stock);
        $this->assertDatabaseHas('venta', ['id_venta' => $second, 'estado' => 'preparada', 'total' => 21.50]);
    }

    public function test_direct_cheque_is_trackable_idempotent_conciliable_and_reversible(): void
    {
        $sale = $this->sale()->assertCreated()->json('venta_id');
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'confirmada'])->assertOk();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $data = ['tipo' => 'pago', 'metodo_pago' => 'Cheque', 'monto' => 21.50, 'request_key' => (string) Str::uuid()];
        $url = '/saldo/'.$this->client->id.'/movimientos';
        $checks = DB::table('CHEQUE')->count();
        $this->postJson($url, $data)->assertUnprocessable();
        $this->assertDatabaseCount('CHEQUE', $checks);
        $data['datos_medio_pago'] = ['numero_cheque' => 'QA-DIRECTO', 'banco_emisor' => 'Banco QA', 'fecha_cobro' => today()->toDateString()];
        $this->postJson($url, $data)->assertCreated();
        $this->postJson($url, $data)->assertOk();
        $this->assertDatabaseCount('CHEQUE', $checks + 1);
        $check = DB::table('CHEQUE')->where('numero_cheque', 'QA-DIRECTO')->firstOrFail();
        $history = app(FinancialReadService::class)->account($this->client->id)['payments']->first();
        $this->assertSame((int) $check->id_cheque, $history['cheque']['id']);
        $this->assertSame('en_cartera', $history['cheque']['estado']);
        $this->assertDatabaseHas('cobro_venta', ['id_cobro' => $check->id_cobro, 'id_venta' => $sale, 'monto_aplicado' => 21.50]);
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'pagada'])->assertUnprocessable();
        $this->patchJson('/g2/api/cheques/'.$check->id_cheque.'/depositar')->assertOk();
        $period = app(BankPeriodService::class)->forDate(today()->toDateString(), $this->admin->id);
        $bank = MovimientoBancario::create(['id_periodo' => $period->id_periodo, 'id_usuario' => $this->admin->id,
            'fecha_operacion' => today(), 'monto' => 21.50, 'signo_monto' => 'ingreso', 'descripcion_original' => 'QA directo',
            'descripcion_normalizada' => 'QA DIRECTO', 'tipo_movimiento' => 'cobranza_cliente', 'estado_conciliacion' => 'pendiente']);
        app(BankReconciliationService::class)->reconcile([$bank->id_movimiento_bancario], [['origen' => 'cheque', 'id' => $check->id_cheque]], (string) Str::uuid(), $this->admin->id);
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'pagada'])->assertOk();
        $this->patchJson('/g2/api/cheques/'.$check->id_cheque.'/rechazar', ['motivo' => 'Rechazo verificado'])->assertOk();
        $this->patchJson('/g2/api/cheques/'.$check->id_cheque.'/rechazar', ['motivo' => 'Rechazo verificado'])->assertOk();
        $this->assertEquals(21.50, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
        $this->assertDatabaseHas('venta', ['id_venta' => $sale, 'estado' => 'confirmada']);
    }

    public function test_g1_cheque_request_preserves_details_and_rejects_key_reuse_with_other_cheque(): void
    {
        $this->sale()->assertCreated();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $url = '/api/v1/finanzas/clientes/'.$this->client->id.'/pagos';
        $data = ['request_key' => (string) Str::uuid(), 'monto' => 5.25, 'medio_pago' => 'cheque',
            'datos_medio_pago' => ['numero_cheque' => 'QA-G1', 'banco_emisor' => 'Banco de prueba', 'fecha_cobro' => today()->toDateString()]];
        $this->postJson($url, $data)->assertCreated();
        $data['datos_medio_pago']['numero_cheque'] = 'OTRO';
        $this->postJson($url, $data)->assertConflict();
        $this->assertDatabaseHas('CHEQUE', ['numero_cheque' => 'QA-G1', 'estado' => 'en_cartera']);
        $this->assertDatabaseMissing('CHEQUE', ['numero_cheque' => 'OTRO']);
    }

    public function test_return_is_persisted_with_real_customer_and_delivery_state_but_without_stock_adjustment(): void
    {
        [$delivery, $first, $second] = $this->delivery();
        $this->patchJson('/g2/api/entregas/'.$delivery.'/pedidos/'.$first.'/entregado')->assertOk();
        $rendition = DB::table('RENDICION')->where('id_entrega', $delivery)->firstOrFail();
        $stock = (int) $this->product->fresh()->stock;
        $data = ['id_pedido' => $second, 'motivo' => 'Local cerrado', 'cliente' => 'No confiar en este nombre'];
        $url = '/g2/api/rendiciones/'.$rendition->id_rendicion.'/devoluciones';
        $id = $this->postJson($url, $data)->assertCreated()->json('data.id_devolucion');
        $this->postJson($url, $data)->assertCreated()->assertJsonPath('data.id_devolucion', $id)->assertJsonPath('data.repetido', true);
        $this->assertDatabaseHas('g2_rendicion_devoluciones', ['id' => $id, 'id_cliente' => $this->client->id, 'id_pedido' => $second]);
        $this->assertDatabaseHas('entrega_pedido', ['id_entrega' => $delivery, 'id_venta' => $second, 'estado_pedido_entrega' => 'no_entregada']);
        $this->assertDatabaseHas('remito', ['id_entrega' => $delivery, 'id_venta' => $second, 'estado' => 'no_entregado']);
        $this->assertDatabaseHas('entrega', ['id_entrega' => $delivery, 'estado' => 'finalizada']);
        $this->assertSame($stock, (int) $this->product->fresh()->stock);
        $this->patchJson('/g2/api/entregas/'.$delivery.'/pedidos/'.$second.'/entregado')->assertConflict();
        $this->getJson('/g2/api/rendiciones/'.$rendition->id_rendicion)->assertOk()->assertJsonCount(1, 'data.devoluciones');
        $this->postJson($url, ['id_pedido' => $first, 'motivo' => 'Ya entregado'])->assertConflict();
    }

    public function test_difference_uses_real_sales_and_declared_collections_then_requires_audited_resolution(): void
    {
        [$delivery, $first] = $this->delivery();
        $this->patchJson('/g2/api/entregas/'.$delivery.'/pedidos/'.$first.'/entregado')->assertOk();
        $rendition = DB::table('RENDICION')->where('id_entrega', $delivery)->firstOrFail();
        $id = $rendition->id_rendicion;
        DB::table('RENDICION_COBRO')->where('id_rendicion', $id)->update(['monto' => 10]);
        $url = '/g2/api/rendiciones/'.$id;
        $this->postJson($url.'/diferencias', ['total_esperado' => 0, 'motivo' => 'Importe manipulado'])->assertUnprocessable();
        $incident = $this->postJson($url.'/diferencias', ['motivo' => 'Abono parcial informado'])->assertCreated()
            ->assertJsonPath('data.total_esperado', 21.5)->assertJsonPath('data.total_rendido', 10)
            ->assertJsonPath('data.diferencia', -11.5)->assertJsonPath('data.diferencia_pendiente', true)->json('data.id_diferencia');
        $this->getJson($url)->assertOk()->assertJsonPath('data.diferencia.id', $incident);
        $this->patchJson($url.'/revisar', ['accion' => 'aprobar'])->assertConflict();
        $this->patchJson($url.'/diferencias/'.$incident.'/resolver', ['resolucion' => 'Se verifica pago parcial, queda deuda en cuenta.'])->assertOk();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $this->patchJson($url.'/revisar', ['accion' => 'aprobar'])->assertOk();
        $this->assertDatabaseHas('g2_rendicion_diferencias', ['id' => $incident, 'diferencia_pendiente' => 0, 'resuelto_por' => $this->admin->id]);
        $this->assertEquals(33.0, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
        $this->assertDatabaseHas('g2_operacion_eventos', ['dominio' => 'rendiciones', 'registro' => (string) $id, 'evento' => 'diferencia_resuelta']);
    }

    public function test_incident_routes_enforce_ownership_and_closed_renditions_cannot_change(): void
    {
        [$delivery, $first, $second] = $this->delivery();
        $this->patchJson('/g2/api/entregas/'.$delivery.'/pedidos/'.$first.'/entregado')->assertOk();
        $id = DB::table('RENDICION')->where('id_entrega', $delivery)->value('id_rendicion');
        $other = User::factory()->create(['rol' => 'repartidor']);
        $this->signInAs($other);
        $this->postJson('/g2/api/rendiciones/'.$id.'/devoluciones', ['id_pedido' => $second, 'motivo' => 'No es mi entrega'])->assertNotFound();
        $this->signInAs($this->admin);
        DB::table('RENDICION')->where('id_rendicion', $id)->update(['estado' => 'rechazada']);
        $this->postJson('/g2/api/rendiciones/'.$id.'/diferencias', ['motivo' => 'Ya cerrada'])->assertConflict();
    }

    public function test_exact_difference_excludes_orders_already_reviewed_in_another_delivery_tranche(): void
    {
        [$delivery, $first, $second] = $this->delivery();
        $this->patchJson('/g2/api/entregas/'.$delivery.'/pedidos/'.$first.'/entregado')->assertOk();
        $firstRendition = DB::table('RENDICION')->where('id_entrega', $delivery)->value('id_rendicion');
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $this->patchJson('/g2/api/rendiciones/'.$firstRendition.'/revisar', ['accion' => 'aprobar'])->assertOk();
        $this->patchJson('/g2/api/entregas/'.$delivery.'/pedidos/'.$second.'/entregado')->assertOk();
        $next = DB::table('RENDICION')->where('id_entrega', $delivery)->where('estado', 'pendiente')->value('id_rendicion');
        $this->assertNotEquals($firstRendition, $next);
        $this->postJson('/g2/api/rendiciones/'.$next.'/diferencias', [])->assertCreated()
            ->assertJsonPath('data.total_esperado', 21.5)->assertJsonPath('data.total_rendido', 21.5)
            ->assertJsonPath('data.diferencia', 0)->assertJsonPath('data.diferencia_pendiente', false);
    }

    public function test_payment_and_sale_views_render_real_cheque_and_idempotency_controls(): void
    {
        $this->sale()->assertCreated();
        config(['services.balances.transport' => 'internal', 'services.billing.transport' => 'internal',
            'services.balances.client_path' => '/api/v1/finanzas/clientes/{id}/saldo',
            'services.billing.payments_path' => '/api/v1/finanzas/cobros']);
        $views = [
            'cliente' => $this->get('/clientes/'.$this->client->id)->assertOk()->assertSee('datos_medio_pago[numero_cheque]', false),
            'saldos' => $this->get('/saldo')->assertOk()->assertSee('data-cheque-fields', false),
            'ventas' => $this->get('/ventas')->assertOk()->assertSee('pos_venta_pendiente_', false),
            'rendiciones' => $this->get('/rendiciones')->assertOk(),
        ];
        $capture = getenv('ERP_OPERATIONS_CAPTURE');
        if (is_string($capture) && is_dir($capture)) {
            foreach ($views as $name => $response) {
                file_put_contents($capture.'/'.$name.'.html', $response->getContent());
            }
        }
        $this->postJson('/saldo/'.$this->client->id.'/movimientos', ['tipo' => 'pago', 'metodo_pago' => 'Efectivo', 'monto' => 1.005])->assertUnprocessable();
    }
}
