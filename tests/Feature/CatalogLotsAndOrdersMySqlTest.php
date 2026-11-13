<?php

namespace Tests\Feature;

use App\Modules\Catalogo\Services\CatalogOrderService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Login\Models\User;
use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Models\Lote;
use App\Modules\Stock\Services\LotService;
use App\Modules\Stock\Services\StockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\UsesG3Database;

class CatalogLotsAndOrdersMySqlTest extends TestCase
{
    use UsesG3Database;
    private User $actor;
    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDatabaseTransaction();
        $this->actor = User::where('usuario', 'qa-fixture-admin')->firstOrFail();
        $this->product = Producto::where('codigo', 'QA-FIXTURE-BASE')->firstOrFail();
        config(['ventas.integracion' => 'internal', 'services.internal_api.token' => 'catalog-test-service',
            'services.internal_api.user_id' => $this->actor->id, 'services.catalog.transport' => 'internal',
            'services.sales.transport' => 'internal', 'services.balances.transport' => 'internal', 'services.billing.transport' => 'internal']);
        Http::preventStrayRequests();
    }

    private function lot(array $changes = []): array
    {
        return [...['id_producto' => $this->product->id_producto, 'nro_lote' => 'LOTE-QA', 'cantidad' => 10,
            'fecha_vencimiento' => today()->addDays(20)->toDateString(), 'asociar_ingreso' => false], ...$changes];
    }

    public function test_lot_filters_accept_browser_query_string_days(): void
    {
        app(LotService::class)->register($this->lot(), $this->actor->id);
        $this->signInAs($this->actor);
        foreach ([7, 15, 30] as $days) {
            $this->getJson('/inventario/lotes/datos?estado=proximo&dias='.$days)
                ->assertOk()->assertJsonStructure(['data', 'meta' => ['total'], 'alertas' => ['proximos']]);
            $this->getJson('/api/v1/lotes?dias='.$days)->assertOk();
        }
    }

    private function order(array $changes = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'receipt_token' => bin2hex(random_bytes(32)),
            'nombre' => 'Comercio de prueba', 'email' => 'pedido@example.test', 'telefono' => '2923456789',
            'acepta_contacto' => true, 'items' => [['id' => $this->product->id_producto, 'cantidad' => 2]]], ...$changes];
    }

    public function test_new_lot_ingress_and_existing_stock_assignment_have_different_effects(): void
    {
        $first = app(LotService::class)->register($this->lot(), $this->actor->id);
        $this->assertEquals(1000, $this->product->fresh()->stock);
        $this->assertSame(10, $first->cantidad_actual);
        $this->assertDatabaseCount('movimiento_stock', 0);
        $second = app(LotService::class)->register($this->lot(['nro_lote' => 'INGRESO', 'asociar_ingreso' => true]), $this->actor->id);
        $this->assertEquals(1010, $this->product->fresh()->stock);
        $this->assertSame(10, $second->cantidad_actual);
        $this->assertDatabaseCount('movimiento_stock', 1);
        $this->assertDatabaseHas('stock_lote_movimientos', ['id_lote' => $second->id_lote, 'delta' => 10]);
    }

    public function test_expired_controlled_stock_is_not_publicly_available_and_sale_rolls_back(): void
    {
        $lot = app(LotService::class)->register($this->lot(['cantidad' => 1000, 'fecha_vencimiento' => today()->subDay()->toDateString()]), $this->actor->id);
        $this->assertSame(0, $this->product->fresh()->stock_disponible);
        try {
            app(StockService::class)->registrarMovimiento($this->product, 'venta', 1, 'No vender vencidos', $this->actor->id);
            $this->fail('Debe rechazar mercadería vencida.');
        } catch (ValidationException) {}
        $this->assertEquals(1000, $this->product->fresh()->stock);
        $this->assertSame(1000, $lot->fresh()->cantidad_actual);
        $this->assertDatabaseCount('movimiento_stock', 0);
        $this->getJson('/api/v1/catalogo/'.$this->product->id_producto)->assertOk()->assertJsonPath('data.disponibilidad', 'sin-stock');
    }

    public function test_fefo_consumes_earlier_lot_and_reversal_restores_original_lot(): void
    {
        $later = app(LotService::class)->register($this->lot(), $this->actor->id);
        $earlier = app(LotService::class)->register($this->lot(['nro_lote' => 'TEMPRANO', 'fecha_vencimiento' => today()->addDays(5)->toDateString()]), $this->actor->id);
        $client = Cliente::factory()->create(['creado_por' => $this->actor->id]);
        $sale = DB::table('venta')->insertGetId(['id_cliente' => $client->id, 'fecha' => today(), 'total' => 100,
            'descuento_global' => 0, 'numFactura' => 'LOT-QA', 'estado' => 'confirmada', 'observaciones' => '', 'id_usuario' => $this->actor->id], 'id_venta');
        app(StockService::class)->registrarMovimiento($this->product, 'venta', 6, 'Salida trazada', $this->actor->id, idVenta: $sale);
        $this->assertSame(4, $earlier->fresh()->cantidad_actual);
        $this->assertSame(10, $later->fresh()->cantidad_actual);
        app(StockService::class)->registrarMovimiento($this->product, 'devolucion', 6, 'Reversión', $this->actor->id, idVenta: $sale, esIncremento: true);
        $this->assertSame(10, $earlier->fresh()->cantidad_actual);
        $this->assertEquals(1000, $this->product->fresh()->stock);
        $this->assertEquals(0, DB::table('stock_lote_movimientos')->where('id_venta', $sale)->sum('delta'));
    }

    public function test_historical_lots_are_not_adopted_or_applied_implicitly(): void
    {
        $lot = Lote::create(['id_producto' => $this->product->id_producto, 'nro_lote' => 'HIST', 'cantidad_inicial' => 2000,
            'cantidad_actual' => 2000, 'fecha_vencimiento' => today()->subDay(), 'estado_trazabilidad' => 'pendiente_revision']);
        $this->assertSame(1000, $this->product->fresh()->stock_disponible);
        $this->postJson('/inventario/lotes/'.$lot->id_lote.'/adoptar', ['confirmar' => true])->assertStatus(409);
        $this->assertSame('pendiente_revision', $lot->fresh()->estado_trazabilidad);
    }

    public function test_public_category_parent_selects_descendant_products_and_cycles_are_rejected(): void
    {
        $root = $this->postJson('/api/categorias', ['nombre' => 'Almacén QA'])->assertCreated()->json('data.id_categoria');
        $child = $this->postJson('/api/categorias', ['nombre' => 'Pastas QA', 'id_categoria_padre' => $root])->assertCreated()->json('data.id_categoria');
        $this->product->update(['id_categoria' => $child]);
        $this->getJson('/api/v1/catalogo?categoria='.$root)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.categoria_ruta.0.id', (string) $root);
        $this->putJson('/api/categorias/'.$root, ['nombre' => 'Almacén QA', 'id_categoria_padre' => $child])->assertUnprocessable();
        $this->deleteJson('/api/categorias/'.$root)->assertStatus(400);
    }

    public function test_uploaded_photo_is_normalized_and_delivered_to_public_catalog(): void
    {
        $response = $this->post('/api/productos', ['codigo' => 'FOTO-QA', 'nombre' => 'Foto QA', 'descripcion' => 'Producto con fotografía',
            'precio_unitario' => '25.50', 'id_categoria' => $this->product->id_categoria, 'id_marca' => $this->product->id_marca,
            'imagen_archivo' => UploadedFile::fake()->image('foto.jpg', 32, 32)], ['Accept' => 'application/json']);
        $response->assertCreated();
        $product = Producto::where('codigo', 'FOTO-QA')->firstOrFail();
        $image = $product->imagen;
        $this->assertMatchesRegularExpression('~^/assets/catalogo/producto-[a-f0-9-]+\.webp$~', $image);
        $this->beforeApplicationDestroyed(function () use ($image) { if (str_starts_with($image, '/assets/catalogo/producto-')) @unlink(public_path(ltrim($image, '/'))); });
        $this->assertFileExists(public_path(ltrim($image, '/')));
        $this->getJson('/api/v1/catalogo/'.$product->id_producto)->assertOk()->assertJsonPath('data.imagen', $image);
    }

    public function test_image_alias_cannot_bypass_image_validation(): void
    {
        $this->post('/api/productos', ['codigo' => 'BAD-FOTO', 'descripcion' => 'Archivo inválido', 'precio_unitario' => 10,
            'id_categoria' => $this->product->id_categoria, 'id_marca' => $this->product->id_marca,
            'imagen' => UploadedFile::fake()->create('payload.php', 1, 'text/plain')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseMissing('producto', ['codigo' => 'BAD-FOTO']);
    }

    public function test_nonexistent_local_picture_path_is_rejected(): void
    {
        $this->postJson('/api/productos', ['codigo' => 'BAD-PATH', 'descripcion' => 'Foto inexistente', 'precio_unitario' => 10,
            'id_categoria' => $this->product->id_categoria, 'id_marca' => $this->product->id_marca,
            'imagen_url' => '/assets/catalogo/no-existe-'.Str::uuid().'.webp'])->assertUnprocessable();
        $this->assertDatabaseMissing('producto', ['codigo' => 'BAD-PATH']);
    }

    public function test_failed_product_transaction_removes_just_the_new_uploaded_file(): void
    {
        $before = glob(public_path('assets/catalogo/producto-*.webp'));
        $this->post('/api/productos', ['codigo' => 'FOTO-ROLLBACK', 'descripcion' => 'Error de asociación', 'precio_unitario' => 10,
            'id_categoria' => $this->product->id_categoria, 'id_marca' => $this->product->id_marca, 'id_ubicacion' => 2147483600,
            'imagen_archivo' => UploadedFile::fake()->image('foto.jpg', 32, 32)], ['Accept' => 'application/json'])->assertStatus(500);
        $this->assertDatabaseMissing('producto', ['codigo' => 'FOTO-ROLLBACK']);
        $this->assertSame($before, glob(public_path('assets/catalogo/producto-*.webp')));
    }

    public function test_public_order_is_persisted_once_without_creating_sale_or_stock_change(): void
    {
        $input = $this->order();
        $first = app(CatalogOrderService::class)->submit($input);
        $again = app(CatalogOrderService::class)->submit($input);
        $this->assertSame($first['receipt_url'], $again['receipt_url']);
        $this->assertTrue($again['repetido']);
        $this->assertDatabaseCount('g1_catalog_orders', 1);
        $this->assertDatabaseCount('g1_catalog_order_items', 1);
        $this->assertDatabaseCount('venta', 0);
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 0);
        $this->assertEquals(1000, $this->product->fresh()->stock);
        $this->get($first['receipt_url'])->assertOk()->assertSeeText('Recibimos tu selección')->assertDontSee('pedido@example.test');
        $this->get('/catalogo/pedido/'.str_repeat('0', 64))->assertNotFound();
    }

    public function test_order_replay_with_changed_payload_is_rejected(): void
    {
        $input = $this->order();
        app(CatalogOrderService::class)->submit($input);
        $this->postJson('/catalogo/pedidos', [...$input, 'nombre' => 'Otro comercio'])->assertStatus(409);
        $this->assertDatabaseCount('g1_catalog_orders', 1);
    }

    public function test_public_browsing_does_not_exhaust_the_five_order_submissions_limit(): void
    {
        auth()->logout();
        foreach (range(1, 8) as $attempt) {
            $this->getJson('/api/v1/catalogo')->assertOk();
        }
        $this->postJson('/api/v1/auth/login', ['usuario' => 'catalogo-no-existe-qa', 'password' => 'Incorrecta1234'])->assertUnprocessable();
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/catalogo/pedidos', $this->order())->assertCreated();
        }
        $this->postJson('/catalogo/pedidos', $this->order())->assertStatus(429);
        $this->assertDatabaseCount('g1_catalog_orders', 5);
    }

    public function test_review_without_confirmation_and_conversion_replay_are_safe(): void
    {
        app(CatalogOrderService::class)->submit($this->order());
        $id = DB::table('g1_catalog_orders')->value('id');
        $this->postJson('/pedidos-catalogo/'.$id.'/revisar', ['accion' => 'revisar'])->assertOk()->assertJsonPath('data.estado', 'en_revision');
        $client = Cliente::factory()->create(['creado_por' => $this->actor->id, 'tipo_cliente' => 'mayorista']);
        $input = ['accion' => 'convertir', 'cliente_id' => $client->id, 'lista' => 'mayorista', 'confirmar_revision' => true];
        $response = $this->postJson('/pedidos-catalogo/'.$id.'/revisar', $input)->assertOk();
        $this->assertNotNull($response->json('data.venta_id'));
        $this->postJson('/pedidos-catalogo/'.$id.'/revisar', $input)->assertOk()->assertJsonPath('data.repetido', true);
        $this->assertDatabaseCount('venta', 1);
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
        $this->assertEquals(998, $this->product->fresh()->stock);
        $this->assertDatabaseCount('g1_catalog_order_events', 3);
        Http::assertNothingSent();
    }

    public function test_partial_purchase_receipts_reuse_the_lot_without_double_stock_ingress(): void
    {
        $supplier = DB::table('proveedor')->value('id_proveedor');
        $purchase = $this->postJson('/api/compras', ['id_proveedor' => $supplier,
            'items' => [['id_producto' => $this->product->id_producto, 'cantidad' => 5, 'precio_unitario' => '10.00']]])->assertCreated()->json('data.id_compra');
        $detail = DB::table('detalle_compra')->where('id_compra', $purchase)->first();
        $item = ['id_producto' => $this->product->id_producto, 'id_detalle_compra' => $detail->id_detalle_compra,
            'id_unidad' => $detail->id_unidad, 'cantidad_recibida' => 2, 'nro_lote' => 'LOTE-RECEPCION', 'fecha_vencimiento' => today()->addDays(30)->toDateString()];
        $this->postJson('/api/compras/'.$purchase.'/recepciones', ['items' => [$item]])->assertCreated();
        $this->postJson('/api/compras/'.$purchase.'/recepciones', ['items' => [[...$item, 'fecha_vencimiento' => today()->addDays(40)->toDateString()]]])->assertUnprocessable();
        $this->assertEquals(1002, $this->product->fresh()->stock);
        $this->postJson('/api/compras/'.$purchase.'/recepciones', ['items' => [[...$item, 'cantidad_recibida' => 3]]])->assertCreated();
        $this->assertEquals(1005, $this->product->fresh()->stock);
        $this->assertDatabaseCount('lote', 1);
        $this->assertDatabaseHas('lote', ['nro_lote' => 'LOTE-RECEPCION', 'cantidad_actual' => 5, 'cantidad_inicial' => 5]);
        $this->assertDatabaseCount('recepcion', 2);
        $this->assertDatabaseCount('stock_lote_movimientos', 2);
    }

    public function test_non_admin_cannot_review_requests_or_adopt_lots(): void
    {
        $viewer = User::factory()->create(['rol' => 'contador']);
        $this->signInAs($viewer);
        $this->get('/pedidos-catalogo')->assertRedirect('/dashboard');
        $this->get('/inventario/lotes')->assertOk();
        $this->post('/inventario/lotes', $this->lot())->assertRedirect('/dashboard');
        $this->post('/pedidos-catalogo/1/revisar', ['accion' => 'revisar'])->assertRedirect('/dashboard');
    }
}
