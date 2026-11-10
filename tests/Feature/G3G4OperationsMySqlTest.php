<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Compras\Models\Compra;
use App\Modules\Compras\Models\DetalleCompra;
use App\Modules\Compras\Services\PagoCompraService;
use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\ConciliacionBancaria\Services\BankPeriodService;
use App\Modules\ConciliacionBancaria\Services\BankReconciliationService;
use App\Modules\CuentaCorriente\Services\ClientPaymentService;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\Login\Models\User;
use App\Modules\Productos\Models\Producto;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Stock\Models\UnidadMedida;
use App\Modules\Stock\Services\ConteoFisicoService;
use App\Modules\Stock\Services\ProductUnitService;
use App\Modules\Stock\Services\StockService;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Integraciones\HttpClienteApi;
use App\Modules\Ventas\Integraciones\HttpProductoApi;
use App\Modules\Ventas\Integraciones\HttpStockApi;
use App\Modules\Ventas\Services\StockDeliveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Opt-in explícito: nunca cambia a la BD local habitual ni ejecuta migraciones. */
class G3G4OperationsMySqlTest extends TestCase
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
        return $this->postJson('/api/ventas', array_replace(['cliente_id' => $this->client->id, 'lista' => 'minorista',
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

    private function promo(array $override = []): int
    {
        $id = DB::table('promocion')->insertGetId(array_replace(['nombre' => 'QA combo', 'descripcion' => 'Prueba',
            'total' => 19.36, 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 10,
            'vigencia_desde' => today()->subDay(), 'vigencia_hasta' => today()->addDay(), 'estado' => 1], $override), 'id_promocion');
        DB::table('promocion_producto')->insert(['id_promocion' => $id, 'id_producto' => $this->product->id_producto, 'cantidad' => 2]);

        return $id;
    }

    public function test_sale_preserves_cents_due_date_ledger_and_real_stock(): void
    {
        $response = $this->sale()->assertCreated()->assertJsonPath('total', 21.5)->assertJsonPath('stock_estado', 'aplicado');
        $this->assertDatabaseHas('detalle_venta', ['id_venta' => $response->json('venta_id'), 'precio_unitario' => 10.75, 'subtotal' => 21.50]);
        $this->assertDatabaseHas('venta', ['id_venta' => $response->json('venta_id'), 'fecha_vencimiento' => today()->addDays(30)->toDateString()]);
        $this->assertSame(48, (int) $this->product->fresh()->stock);
        $this->assertDatabaseCount('g4_stock_operations', 1);
        $this->assertDatabaseCount('g3_stock_requests', $this->initialCounts['g3_stock_requests'] + 1);
    }

    public function test_insufficient_internal_stock_rolls_back_sale_ledger_and_delivery(): void
    {
        $sales = DB::table('venta')->count();
        $this->sale(['items' => [['id' => $this->product->id_producto, 'cantidad' => 51, 'descuento' => 0]]])->assertStatus(503);
        $this->assertDatabaseCount('venta', $sales);
        $this->assertDatabaseCount('g4_stock_operations', 0);
        $this->assertSame(50, (int) $this->product->fresh()->stock);
    }

    public function test_stock_uses_locked_fresh_product_not_stale_instance(): void
    {
        $stale = $this->product->replicate();
        $stale->id_producto = $this->product->id_producto;
        $stale->exists = true;
        app(StockService::class)->registrarMovimiento($this->product, 'venta', 30, 'Primera venta', $this->admin->id);
        try {
            app(StockService::class)->registrarMovimiento($stale, 'venta', 30, 'Segunda venta', $this->admin->id);
            $this->fail('Debe rechazar stock insuficiente.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
        }
        $this->assertSame(20, (int) $this->product->fresh()->stock);
    }

    public function test_unit_must_belong_to_product_and_omission_never_uses_global_or_visual_box(): void
    {
        $this->unit(null, 12);
        $resolved = ProductUnitService::resolve((int) $this->product->id_producto);
        $this->assertSame(1.0, (float) $resolved->equivalencia_base);
        $other = $this->product->replicate();
        $other->codigo = 'QA-'.Str::uuid();
        $other->save();
        $foreign = $this->unit((int) $other->id_producto, 24);
        try {
            app(StockService::class)->registrarMovimiento($this->product, 'venta', 1, 'Unidad ajena', $this->admin->id, $foreign->id_unidad);
            $this->fail('Debe rechazar unidad ajena.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('id_unidad', $e->errors());
        }
        $this->assertSame(50, (int) $this->product->fresh()->stock);
    }

    public function test_fractional_unit_cannot_silently_round_inventory(): void
    {
        $this->postJson('/api/productos/'.$this->product->id_producto.'/unidades',
            ['nombre_unidad' => 'Fracción', 'equivalencia_base' => 0.5])->assertStatus(400);
        $this->assertDatabaseCount('unidad_medida', $this->initialCounts['unidad_medida']);
    }

    public function test_stock_request_retry_is_identical_and_changed_payload_is_conflict(): void
    {
        $key = (string) Str::uuid();
        $body = ['request_key' => $key, 'items' => [['id_producto' => $this->product->id_producto, 'cantidad' => -3]]];
        $first = $this->postJson('/api/v1/stock/movimientos', $body)->assertOk()->json('data');
        $second = $this->postJson('/api/v1/stock/movimientos', $body)->assertOk()->json('data');
        $this->assertSame($first, $second);
        $body['items'][0]['cantidad'] = -4;
        $this->postJson('/api/v1/stock/movimientos', $body)->assertConflict();
        $this->assertSame(47, (int) $this->product->fresh()->stock);
    }

    public function test_receipt_over_pending_and_payment_over_debt_cannot_write(): void
    {
        $purchase = $this->purchase();
        $uri = '/api/compras/'.$purchase->id_compra.'/recepciones';
        $body = ['items' => [['id_producto' => $this->product->id_producto, 'cantidad' => 7]]];
        $this->postJson($uri, $body)->assertCreated();
        $this->postJson($uri, $body)->assertStatus(400);
        $this->assertSame(57, (int) $this->product->fresh()->stock);
        $data = ['metodo_pago' => 'efectivo', 'importe' => 80, 'fecha_pago' => today()->toDateString()];
        app(PagoCompraService::class)->registrar($purchase->id_compra, $data, $this->admin->id);
        try {
            app(PagoCompraService::class)->registrar($purchase->id_compra, $data, $this->admin->id);
            $this->fail('Sobrepago');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('excede', $e->getMessage());
        }
        $this->assertSame(27.5, app(PagoCompraService::class)->resumen($purchase->id_compra)['saldo_a_pagar']);
        $this->patchJson('/api/compras/'.$purchase->id_compra.'/cancelar')->assertStatus(422);
        $pending = $this->purchase();
        app(PagoCompraService::class)->registrar($pending->id_compra, $data, $this->admin->id);
        $this->patchJson('/api/compras/'.$pending->id_compra.'/cancelar')->assertUnprocessable();
    }

    public function test_valid_promotion_and_revalidation_when_confirming_expired(): void
    {
        $id = $this->promo();
        $response = $this->sale(['items' => [['id' => $this->product->id_producto, 'cantidad' => 2,
            'descuento' => 1.07, 'id_promocion' => $id]]])->assertCreated()->assertJsonPath('total', 19.36);
        DB::table('promocion')->where('id_promocion', $id)->update(['vigencia_hasta' => today()->subDay()]);
        $this->patchJson('/api/ventas/'.$response->json('venta_id').'/estado', ['estado' => 'confirmada'])->assertUnprocessable();
    }

    public function test_promotion_rejects_inactive_incomplete_and_tampered_discount(): void
    {
        $id = $this->promo();
        foreach ([[1, 1.07], [2, 5], [3, 1.07]] as [$quantity, $discount]) {
            $this->sale(['items' => [['id' => $this->product->id_producto, 'cantidad' => $quantity,
                'descuento' => $discount, 'id_promocion' => $id]]])->assertUnprocessable();
        }
        DB::table('promocion')->where('id_promocion', $id)->update(['estado' => 0]);
        $this->sale(['items' => [['id' => $this->product->id_producto, 'cantidad' => 2, 'descuento' => 1.07, 'id_promocion' => $id]]])->assertUnprocessable();
        $this->assertDatabaseCount('g4_stock_operations', 0);
    }

    public function test_paid_state_requires_real_cash_and_full_payment(): void
    {
        $sale = $this->sale()->assertCreated()->json('venta_id');
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'pagada'])->assertUnprocessable();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        app(ClientPaymentService::class)->register($this->client->id, ['request_key' => (string) Str::uuid(), 'monto' => 10, 'medio_pago' => 'efectivo'], $this->admin);
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'pagada'])->assertUnprocessable();
        app(ClientPaymentService::class)->register($this->client->id, ['request_key' => (string) Str::uuid(), 'monto' => 11.50, 'medio_pago' => 'efectivo'], $this->admin);
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'pagada'])->assertOk()->assertJsonPath('data.estado.valor', 'pagada');
    }

    public function test_unverified_ledger_credit_does_not_certify_paid(): void
    {
        $sale = $this->sale()->assertCreated()->json('venta_id');
        DB::table('cuenta_corriente_movimientos')->insert(['cliente_id' => $this->client->id, 'tipo' => 'pago',
            'descripcion' => 'Sin caja verificable', 'importe' => 21.50, 'created_at' => now(), 'updated_at' => now()]);
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'pagada'])->assertUnprocessable();
    }

    public function test_modify_cancel_restore_use_real_deltas_and_repeat_delivery_is_noop(): void
    {
        $sale = $this->sale()->assertCreated()->json('venta_id');
        $this->putJson('/api/ventas/'.$sale, ['lista' => 'minorista', 'items' => [['id' => $this->product->id_producto, 'cantidad' => 3, 'descuento' => 0]]])->assertOk();
        $this->assertSame(47, (int) $this->product->fresh()->stock);
        $this->deleteJson('/api/ventas/'.$sale, ['motivo' => 'Prueba de anulación'])->assertOk();
        $this->assertSame(50, (int) $this->product->fresh()->stock);
        $this->postJson('/api/ventas/'.$sale.'/restaurar')->assertOk();
        $this->assertSame(47, (int) $this->product->fresh()->stock);
        $operation = DB::table('g4_stock_operations')->where('venta_id', $sale)->orderByDesc('id')->first();
        $this->assertTrue(app(StockDeliveryService::class)->deliver($operation->id));
        $this->assertSame(47, (int) $this->product->fresh()->stock);
    }

    public function test_pdf_and_close_cash_navigation_keep_permissions(): void
    {
        $this->sale()->assertCreated();
        $pdf = $this->get('/saldo/exportar/pdf?modoFecha=todas')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get('/saldo/exportar/pdf?modoFecha=periodo&fechaHasta=2026-11-09')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/saldo/exportar/pdf?modoFecha=periodo&fechaDesde=2026-11-01')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->getJson('/saldo/exportar/pdf?modoFecha=periodo&fechaDesde=2026-11-09&fechaHasta=2026-11-01')->assertUnprocessable();
        $this->get('/ventas')->assertOk()->assertSee('window.location.assign('.json_encode(route('caja.dia')).');', false);
        auth()->logout();
        $this->get('/saldo/exportar/pdf')->assertRedirect('/login');
    }

    public function test_http_failure_is_durable_retry_reuses_key_and_pending_sale_is_blocked(): void
    {
        $sale = $this->sale()->assertCreated()->json('venta_id');
        config(['ventas.integracion' => 'http', 'ventas.servicios.stock.url' => 'https://stock.example']);
        $keys = [];
        $calls = 0;
        Http::fake(function ($request) use (&$keys, &$calls) {
            $keys[] = $request['request_key'];

            return Http::response(['status' => ++$calls === 1 ? 'error' : 'success'], $calls === 1 ? 503 : 200);
        });
        $service = app(StockDeliveryService::class);
        $id = DB::transaction(fn () => $service->enqueue($sale, [$this->product->id_producto => -1], 'modificacion', $this->admin->id));
        $this->assertFalse($service->deliver($id));
        $this->assertDatabaseHas('g4_stock_operations', ['id' => $id, 'estado' => 'pendiente', 'intentos' => 1]);
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'confirmada'])->assertConflict();
        $this->assertTrue($service->deliver($id));
        $this->assertSame($keys[0], $keys[1]);
        $this->assertDatabaseHas('g4_stock_operations', ['id' => $id, 'estado' => 'aplicado', 'intentos' => 2]);
        $this->assertTrue($service->deliver($id));
        $this->assertCount(2, $keys);
    }

    public function test_receipt_identifies_detail_when_same_product_has_two_units(): void
    {
        $purchase = $this->purchase();
        $box = $this->unit(null, 12);
        $second = DetalleCompra::create(['id_compra' => $purchase->id_compra, 'id_producto' => $this->product->id_producto,
            'id_unidad' => $box->id_unidad, 'cantidad' => 2, 'cantidad_recibida' => 0, 'precio_unitario' => 100, 'subtotal' => 200]);
        $uri = '/api/compras/'.$purchase->id_compra.'/recepciones';
        $this->postJson($uri, ['items' => [['id_producto' => $this->product->id_producto, 'cantidad' => 1]]])->assertStatus(400);
        $this->postJson($uri, ['items' => [['id_producto' => $this->product->id_producto, 'id_detalle_compra' => $second->id_detalle_compra, 'cantidad' => 1]]])->assertCreated();
        $this->assertSame(62, (int) $this->product->fresh()->stock);
        $this->assertSame(1, (int) $second->fresh()->cantidad_recibida);
    }

    public function test_purchase_rejects_foreign_unit_and_unit_equivalence_with_history_is_immutable(): void
    {
        $other = $this->product->replicate();
        $other->codigo = 'QA-'.Str::uuid();
        $other->save();
        $foreign = $this->unit((int) $other->id_producto, 12);
        $this->postJson('/api/compras', ['id_proveedor' => $this->supplier, 'items' => [
            ['id_producto' => $this->product->id_producto, 'id_unidad' => $foreign->id_unidad, 'cantidad' => 1, 'precio_unitario' => 10]]])->assertUnprocessable();
        $this->assertDatabaseCount('compra', $this->initialCounts['compra']);
        $unit = $this->unit();
        app(StockService::class)->registrarMovimiento($this->product, 'ingreso', 1, 'Histórico', $this->admin->id, $unit->id_unidad);
        $this->putJson('/api/productos/'.$this->product->id_producto.'/unidades/'.$unit->id_unidad,
            ['equivalencia_base' => 12])->assertConflict();
        $this->assertSame(1.0, (float) $unit->fresh()->equivalencia_base);
    }

    public function test_promotion_cannot_stack_global_discount_or_skip_confirmation_checks(): void
    {
        $promo = $this->promo();
        $items = [['id' => $this->product->id_producto, 'cantidad' => 2, 'descuento' => 1.07, 'id_promocion' => $promo]];
        $this->sale(['items' => $items, 'descuento_global' => ['modo' => 'monto', 'valor' => 1]])->assertUnprocessable();
        $sale = $this->sale(['items' => $items])->assertCreated()->json('venta_id');
        DB::table('promocion')->where('id_promocion', $promo)->update(['estado' => 0]);
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'preparada'])->assertUnprocessable();
    }

    public function test_absolute_adjustment_to_zero_and_stale_count_do_not_silently_overwrite(): void
    {
        $this->postJson('/api/stock/ajustes', ['id_producto' => $this->product->id_producto, 'cantidad' => 0,
            'tipo' => 'fijar', 'motivo' => 'Conteo completo'])->assertCreated();
        $this->assertSame(0, (int) $this->product->fresh()->stock);
        $counts = app(ConteoFisicoService::class);
        $session = $counts->iniciarSesion([], $this->admin->id);
        $counts->registrarItem($session->id_sesion, $this->product->id_producto, 4);
        app(StockService::class)->registrarMovimiento($this->product, 'ingreso', 2, 'Llegada posterior', $this->admin->id);
        try {
            $counts->finalizarSesion($session->id_sesion, true, $this->admin->id);
            $this->fail('Conteo obsoleto');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cambió', $e->getMessage());
        }
        $this->assertSame(2, (int) $this->product->fresh()->stock);
        $counts->registrarItem($session->id_sesion, $this->product->id_producto, 4);
        $counts->finalizarSesion($session->id_sesion, true, $this->admin->id);
        $this->assertSame(4, (int) $this->product->fresh()->stock);
        $this->assertDatabaseHas('MOVIMIENTO_STOCK', ['id_sesion_conteo' => $session->id_sesion]);
    }

    public function test_balance_screen_payment_uses_cash_idempotency_and_can_certify_paid(): void
    {
        $sale = $this->sale()->assertCreated()->json('venta_id');
        $body = ['tipo' => 'pago', 'metodo_pago' => 'Efectivo', 'monto' => 21.50, 'request_key' => (string) Str::uuid()];
        $url = '/saldo/'.$this->client->id.'/movimientos';
        $this->postJson($url, $body)->assertUnprocessable();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $this->postJson($url, $body)->assertCreated()->assertJsonPath('saldo', 0);
        $this->postJson($url, $body)->assertOk()->assertJsonPath('repetido', true);
        $this->assertDatabaseCount('cuenta_corriente_pagos', 1);
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 1);
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'pagada'])->assertOk();
    }

    public function test_cheque_only_certifies_paid_after_reconciliation_and_rejection_restores_phase_and_debt(): void
    {
        $sale = $this->sale()->assertCreated()->json('venta_id');
        $this->patchJson('/api/ventas/'.$sale.'/estado', ['estado' => 'confirmada'])->assertOk();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $driver = User::factory()->create(['rol' => 'repartidor']);
        $rendition = Rendicion::create(['id_repartidor' => $driver->id,
            'fecha' => now(), 'total_rendido' => 21.50, 'estado' => 'pendiente']);
        RendicionCobro::create(['id_rendicion' => $rendition->id_rendicion,
            'id_cliente' => $this->client->id, 'id_factura' => $sale, 'monto' => 21.50, 'medio_pago' => 'cheque',
            'fecha_registro' => now(), 'datos_medio_pago' => ['numero_cheque' => 'QA-CHECK', 'banco_emisor' => 'Banco QA',
                'fecha_cobro' => today()->toDateString()]]);
        $this->patchJson('/g2/api/rendiciones/'.$rendition->id_rendicion.'/revisar', ['accion' => 'aprobar'])->assertOk();
        $check = DB::table('CHEQUE')->where('numero_cheque', 'QA-CHECK')->firstOrFail();
        $paidUrl = '/api/ventas/'.$sale.'/estado';
        $this->patchJson($paidUrl, ['estado' => 'pagada'])->assertUnprocessable();
        $this->patchJson('/g2/api/cheques/'.$check->id_cheque.'/depositar')->assertOk();
        $this->patchJson($paidUrl, ['estado' => 'pagada'])->assertUnprocessable();
        $period = app(BankPeriodService::class)->forDate(today()->toDateString(), $this->admin->id);
        $bank = MovimientoBancario::create(['id_periodo' => $period->id_periodo,
            'id_usuario' => $this->admin->id, 'fecha_operacion' => today(), 'monto' => 21.50, 'signo_monto' => 'ingreso',
            'descripcion_original' => 'Cheque QA', 'descripcion_normalizada' => 'CHEQUE QA',
            'tipo_movimiento' => 'cobranza_cliente', 'estado_conciliacion' => 'pendiente']);
        app(BankReconciliationService::class)->reconcile([$bank->id_movimiento_bancario],
            [['origen' => 'cheque', 'id' => $check->id_cheque]], (string) Str::uuid(), $this->admin->id);
        $this->patchJson($paidUrl, ['estado' => 'pagada'])->assertOk();
        $this->patchJson('/g2/api/cheques/'.$check->id_cheque.'/rechazar', ['motivo' => 'Rechazo bancario probado'])->assertOk();
        $this->assertDatabaseHas('venta', ['id_venta' => $sale, 'estado' => 'confirmada']);
        $this->assertSame(21.5, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
        $this->assertDatabaseHas('venta_historial', ['id_venta' => $sale, 'accion' => 'reversion_financiera']);
    }
}
