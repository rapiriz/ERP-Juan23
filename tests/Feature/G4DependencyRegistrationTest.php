<?php

namespace Tests\Feature;

use App\Modules\Login\Enums\UserRole;
use App\Modules\Login\Enums\UserStatus;
use App\Modules\Login\Models\User;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Controllers\VentaController;
use App\Modules\Ventas\Integraciones\HttpClienteApi;
use App\Modules\Ventas\Integraciones\HttpProductoApi;
use App\Modules\Ventas\Integraciones\HttpStockApi;
use App\Modules\Ventas\Integraciones\MockStockApi;
use App\Services\MockClienteApi;
use App\Services\MockProductoApi;
use App\Services\MockUsuarioApi;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/** Real routes/controllers/container; database reads are fixtures, with no SQL or writes. */
class G4DependencyRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function sessionAsAdmin(): void
    {
        $token = 'g4-registration-test';
        $user = new User;
        $user->forceFill([
            'id' => 1, 'usuario' => 'g4-test', 'nombre' => 'Prueba de integración',
            'rol' => UserRole::ADMINISTRATIVO, 'estado' => UserStatus::ACTIVO,
            'current_session_id' => hash('sha256', $token),
        ]);
        $this->actingAs($user)->withSession([
            'session_token' => $token, 'last_activity' => now()->timestamp,
        ]);
    }

    public function test_default_configuration_resolves_all_original_mock_dependencies(): void
    {
        $this->assertSame('mock', config('ventas.integracion'));
        $this->assertInstanceOf(MockClienteApi::class, app(ClienteGateway::class));
        $this->assertInstanceOf(MockProductoApi::class, app(ProductoGateway::class));
        $stock = app(StockGateway::class);
        $this->assertInstanceOf(MockStockApi::class, $stock);
        $this->assertSame([], $stock->avisos());
        $this->assertInstanceOf(MockUsuarioApi::class, app(MockUsuarioApi::class));
        $this->assertSame(app(ClienteGateway::class), app(ClienteGateway::class));
        $this->assertSame(app(ProductoGateway::class), app(ProductoGateway::class));
        $this->assertSame(app(StockGateway::class), app(StockGateway::class));
        Http::assertNothingSent();
    }

    public function test_http_configuration_selects_existing_adapters_without_contacting_other_groups(): void
    {
        config(['ventas.integracion' => 'http']);
        $this->assertInstanceOf(HttpClienteApi::class, app(ClienteGateway::class));
        $this->assertInstanceOf(HttpProductoApi::class, app(ProductoGateway::class));
        $this->assertInstanceOf(HttpStockApi::class, app(StockGateway::class));
        Http::assertNothingSent();
    }

    public function test_product_picker_returns_only_active_products(): void
    {
        $this->sessionAsAdmin();
        config([
            'ventas.integracion' => 'http',
            'ventas.servicios.productos.url' => 'http://g3.test',
            'ventas.servicios.stock.url' => 'http://g3.test',
            'services.internal_api.token' => 'g4-registration-test',
        ]);
        Http::fake([
            'g3.test/api/v1/productos' => Http::response(['data' => [
                ['id' => 56, 'codigo' => 'P056', 'nombre' => 'Producto activo', 'estado' => 'activo', 'precio_minorista' => 100],
                ['id' => 57, 'codigo' => 'P057', 'nombre' => 'Producto inactivo', 'estado' => 'inactivo', 'precio_minorista' => 200],
            ]]),
            'g3.test/api/v1/stock/56' => Http::response(['disponible' => 8]),
            'g3.test/api/v1/stock/57' => Http::response(['message' => 'Producto inactivo'], 400),
        ]);

        $this->getJson('/api/ventas/productos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 56);
    }

    public function test_sales_page_looks_up_stock_only_for_active_products(): void
    {
        $productos = Mockery::mock(ProductoGateway::class);
        $productos->shouldReceive('todos')->once()->andReturn([
            ['id' => 56, 'nombre' => 'Producto activo', 'estado' => 'activo'],
            ['id' => 57, 'nombre' => 'Producto inactivo', 'estado' => 'inactivo'],
        ]);
        $clientes = Mockery::mock(ClienteGateway::class);
        $clientes->shouldReceive('todos')->once()->andReturn([]);
        $stock = Mockery::mock(StockGateway::class);
        $stock->shouldReceive('disponible')->once()->with(56)->andReturn(8);

        $view = (new VentaController)->index($clientes, $productos, $stock);

        $this->assertCount(1, $view->getData()['productos']);
        $this->assertSame(56, $view->getData()['productos'][0]['id']);
        $this->assertSame(8, $view->getData()['productos'][0]['stock']);
    }

    public function test_sales_store_rejects_inactive_product_ids(): void
    {
        $productos = Mockery::mock(ProductoGateway::class);
        $productos->shouldReceive('todos')->once()->andReturn([
            ['id' => 56, 'estado' => 'activo'],
            ['id' => 57, 'estado' => 'inactivo'],
        ]);
        $request = Request::create('/api/ventas', 'POST', [
            'cliente_id' => 915736,
            'lista' => 'minorista',
            'items' => [['id' => 57, 'cantidad' => 1, 'descuento' => 0]],
        ]);

        try {
            (new VentaController)->store($request, Mockery::mock(ClienteGateway::class), $productos, Mockery::mock(StockGateway::class));
            $this->fail('Inactive products should not pass sales validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.id', $exception->errors());
        }
    }

    public function test_promotions_reject_inactive_product_ids(): void
    {
        $productos = Mockery::mock(ProductoGateway::class);
        $productos->shouldReceive('todos')->once()->andReturn([
            ['id' => 56, 'estado' => 'activo'],
            ['id' => 57, 'estado' => 'inactivo'],
        ]);
        $this->app->instance(ProductoGateway::class, $productos);

        $this->postJson('/api/promociones', [
            'nombre' => 'Promoción de prueba',
            'vigencia_desde' => now()->toDateString(),
            'vigencia_hasta' => now()->addDay()->toDateString(),
            'tipo_descuento' => 'porcentaje',
            'valor' => 10,
            'productos' => [['id_producto' => 57, 'cantidad' => 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('productos.0.id_producto');
    }

    public function test_sales_screen_dispatches_the_real_controller_with_original_mock_catalogues(): void
    {
        $this->sessionAsAdmin();
        // No database operation is expected for the POS GET.
        DB::shouldReceive('table')->never();
        $this->get('/ventas')->assertOk()->assertViewIs('ventas')
            ->assertViewHas('clientes', fn ($items) => $items === app(ClienteGateway::class)->todos())
            ->assertViewHas('productos', fn ($items) => $items === app(ProductoGateway::class)->todos())
            ->assertSee('Supermercado El Norte SRL')->assertSee('Coca-Cola 1.5L')
            ->assertSee('const URL_VENTAS', false)->assertSee('btnConfirmarCobro');
        Http::assertNothingSent();
    }

    public function test_history_dispatches_the_real_controller_with_read_only_query_fixtures(): void
    {
        $this->sessionAsAdmin();
        $ventas = Mockery::mock(Builder::class);
        $ventas->shouldReceive('select')->once()->andReturnSelf();
        $ventas->shouldReceive('orderByDesc')->twice()->andReturnSelf();
        $ventas->shouldReceive('get')->once()->andReturn(collect([(object) [
            'id_venta' => 901, 'id_cliente' => 472891, 'factura' => 'PRUEBA-901',
            'fecha' => '2026-10-22', 'total' => 5000, 'descuento' => 0,
            'estado' => 'confirmada', 'observaciones' => 'Lectura de prueba', 'id_usuario' => 1,
        ]]));
        DB::shouldReceive('table')->with('venta as v')->once()->andReturn($ventas);

        $detalles = Mockery::mock(Builder::class);
        $detalles->shouldReceive('whereIn')->once()->andReturnSelf();
        $detalles->shouldReceive('select')->once()->andReturnSelf();
        $detalles->shouldReceive('get')->once()->andReturn(collect([(object) [
            'id_venta' => 901, 'id_producto' => 1, 'cantidad' => 2,
            'precio_unitario' => 2500, 'descuento' => 0, 'subtotal' => 5000,
        ]]));
        DB::shouldReceive('table')->with('detalle_venta as dv')->once()->andReturn($detalles);

        $cobros = Mockery::mock(Builder::class);
        $cobros->shouldReceive('join')->once()->andReturnSelf();
        $cobros->shouldReceive('whereIn')->once()->andReturnSelf();
        $cobros->shouldReceive('select')->once()->andReturnSelf();
        $cobros->shouldReceive('get')->once()->andReturn(collect());
        DB::shouldReceive('table')->with('cobro_venta as cv')->once()->andReturn($cobros);

        $this->get('/historial-ventas')->assertOk()->assertViewIs('historial-ventas')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['cantidad'] === 1 && $kpis['total_vendido'] == 5000)
            ->assertSee('PRUEBA-901')->assertSee('Supermercado El Norte SRL')
            ->assertSee('Coca-Cola 1.5L')->assertSee('Administrador');
        Http::assertNothingSent();
    }

    public function test_account_balances_dispatch_the_real_controller_with_read_only_query_fixtures(): void
    {
        $this->sessionAsAdmin();
        $movimientos = Mockery::mock(Builder::class);
        $movimientos->shouldReceive('whereIn')->once()->andReturnSelf();
        $movimientos->shouldReceive('orderBy')->twice()->andReturnSelf();
        $movimientos->shouldReceive('get')->once()->andReturn(collect([(object) [
            'cliente_id' => 472891, 'created_at' => '2026-10-22 12:00:00',
            'descripcion' => 'Movimiento de prueba', 'importe' => 1000,
            'venta_id' => null, 'referencia_externa' => null,
        ]]));
        DB::shouldReceive('table')->with('cuenta_corriente_movimientos')->once()->andReturn($movimientos);

        $this->get('/saldo')->assertOk()->assertViewIs('saldo')
            ->assertViewHas('clientes', fn ($items) => count($items[0]['movimientos']) === 4)
            ->assertSee('Supermercado El Norte SRL')->assertSee('Movimiento de prueba')
            ->assertSee('const URL_MOVIMIENTOS', false);
        Http::assertNothingSent();
    }
}
