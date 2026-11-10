<?php

namespace Tests\Feature;

use App\Modules\Login\Enums\UserRole;
use App\Modules\Login\Enums\UserStatus;
use App\Modules\Login\Models\User;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Integraciones\HttpClienteApi;
use App\Modules\Ventas\Integraciones\HttpProductoApi;
use App\Modules\Ventas\Integraciones\HttpStockApi;
use App\Modules\Ventas\Integraciones\MockStockApi;
use App\Services\MockClienteApi;
use App\Services\MockProductoApi;
use App\Services\MockUsuarioApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Controladores reales contra SQLite de prueba; ningún acceso a la BD habitual. */
class G4DependencyRegistrationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_sales_screen_dispatches_the_real_controller_with_original_mock_catalogues(): void
    {
        $this->sessionAsAdmin();
        $this->get('/ventas')->assertOk()->assertViewIs('ventas.ventas')
            ->assertViewHas('clientes', fn ($items) => $items === app(ClienteGateway::class)->todos())
            ->assertViewHas('productos', fn ($items) => $items === app(ProductoGateway::class)->todos())
            ->assertSee('Supermercado El Norte SRL')->assertSee('Coca-Cola 1.5L');
        Http::assertNothingSent();
    }

    public function test_history_dispatches_the_real_controller_with_read_only_query_fixtures(): void
    {
        $this->sessionAsAdmin();
        DB::table('venta')->insert(['id_venta' => 901, 'id_cliente' => 472891, 'numFactura' => 'PRUEBA-901',
            'fecha' => today(), 'total' => 5000, 'descuento_global' => 0, 'estado' => 'confirmada', 'observaciones' => 'Lectura de prueba', 'id_usuario' => 1]);
        DB::table('detalle_venta')->insert(['id_venta' => 901, 'id_producto' => 1, 'cantidad' => 2,
            'precio_unitario' => 2500, 'descuento' => 0, 'subtotal' => 5000]);
        $this->get('/historial-ventas')->assertOk()->assertViewIs('historial-ventas.historial-ventas')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['cantidad'] === 1 && $kpis['total_vendido'] == 5000)
            ->assertSee('PRUEBA-901')->assertSee('Supermercado El Norte SRL')->assertSee('Coca-Cola 1.5L');
        $this->assertDatabaseCount('venta', 1);
        Http::assertNothingSent();
    }

    public function test_account_balances_dispatch_the_real_controller_with_read_only_query_fixtures(): void
    {
        $this->sessionAsAdmin();
        DB::table('cuenta_corriente_movimientos')->insert(['cliente_id' => 472891, 'tipo' => 'pago', 'created_at' => now(),
            'updated_at' => now(), 'descripcion' => 'Movimiento de prueba', 'importe' => 1000]);
        $this->get('/saldo')->assertOk()->assertViewIs('saldos.saldo')
            ->assertViewHas('clientes', fn ($items) => collect($items[0]['movimientos'])->contains('descripcion', 'Movimiento de prueba'))
            ->assertSee('Supermercado El Norte SRL');
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
        Http::assertNothingSent();
    }
}
