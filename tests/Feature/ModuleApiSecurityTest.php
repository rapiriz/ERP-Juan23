<?php

namespace Tests\Feature;

use App\Modules\ConciliacionBancaria\Controllers\MovimientoBancarioController;
use App\Modules\Login\Enums\UserRole;
use App\Modules\Login\Models\User;
use App\Modules\Login\Services\ApiTokenService;
use App\Modules\Productos\Controllers\CategoriaController;
use App\Modules\Productos\Controllers\ProductoController;
use App\Modules\Rendiciones\Controllers\Api\V1\CobroApiController;
use App\Modules\Stock\Controllers\StockController;
use App\Support\UsuarioActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ModuleApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function privateEndpoints(): array
    {
        return [
            ['GET', '/api/v1/cobros?cliente_id=1'],
            ['GET', '/api/v1/rendiciones'],
            ['GET', '/api/movimientos-bancarios'],
            ['GET', '/api/movimientos-bancarios/1'],
            ['GET', '/api/cobranzas-erp'],
            ['POST', '/api/extractos/importar'],
            ['GET', '/api/v1/productos?interno=1'],
            ['GET', '/api/productos/1?interno=1'],
            ['GET', '/api/productos/codigo/P001?interno=1'],
            ['GET', '/api/categorias'],
            ['POST', '/api/productos'],
            ['PUT', '/api/productos/1'],
            ['DELETE', '/api/productos/1'],
            ['POST', '/api/proveedores'],
            ['POST', '/api/compras'],
            ['POST', '/api/v1/stock/movimientos'],
            ['POST', '/api/stock/ventas'],
            ['POST', '/api/stock/devoluciones'],
            ['PATCH', '/api/stock/1/descontar'],
            ['GET', '/api/promociones'],
            ['POST', '/api/promociones'],
            ['PUT', '/api/promociones/1'],
            ['DELETE', '/api/promociones/1'],
            ['POST', '/api/ventas'],
            ['PATCH', '/api/ventas/1/estado'],
            ['POST', '/api/cuenta-corriente/cobros'],
        ];
    }

    #[DataProvider('privateEndpoints')]
    public function test_guests_cannot_reach_private_handlers(string $method, string $path): void
    {
        $this->withHeaders(['X-User-Id' => '1', 'X-User-Role' => 'administrativo'])
            ->json($method, $path)->assertUnauthorized();
    }

    public function test_admin_bearer_can_write_and_the_actor_cannot_be_forged(): void
    {
        $user = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $token = app(ApiTokenService::class)->issue($user, request())['token'];
        $before = User::count();
        $this->mock(ProductoController::class)->shouldReceive('store')->once()
            ->andReturnUsing(fn (Request $request) => response()->json([
                'actor' => UsuarioActual::id($request),
            ], 201));

        $this->withToken($token)->withHeader('X-User-Id', '999')
            ->postJson('/api/productos', ['id_usuario' => 999])->assertCreated()
            ->assertJsonPath('actor', $user->id);
        $this->assertSame($before, User::count());
        $this->assertFalse(Schema::hasTable('usuario'));
    }

    public function test_repartidor_can_read_g3_but_cannot_change_its_master_data(): void
    {
        $this->signInAs(User::factory()->create(['rol' => UserRole::REPARTIDOR]));
        $this->mock(CategoriaController::class)->shouldReceive('index')->once()
            ->andReturn(response()->json(['data' => []]));
        $this->getJson('/api/categorias')->assertOk();
        $this->postJson('/api/productos', [])->assertForbidden();
        $this->postJson('/api/compras', [])->assertForbidden();
        $this->postJson('/api/promociones', [])->assertForbidden();
        $this->getJson('/api/productos?interno=1')->assertForbidden();
        $this->getJson('/api/v1/cobros?cliente_id=1')->assertForbidden();
    }

    public function test_contador_cannot_write_sales_stock_or_current_accounts(): void
    {
        $this->signInAs(User::factory()->create(['rol' => UserRole::CONTADOR]));
        $this->postJson('/api/ventas', [])->assertForbidden();
        $this->putJson('/api/ventas/1', [])->assertForbidden();
        $this->postJson('/api/v1/stock/movimientos', [])->assertForbidden();
        $this->postJson('/api/cuenta-corriente/cobros', [])->assertForbidden();
        $this->postJson('/saldo/1/movimientos', [])->assertRedirect(route('dashboard'));
    }

    public function test_financial_queries_accept_session_and_service_reads(): void
    {
        $this->mock(CobroApiController::class)->shouldReceive('index')->twice()
            ->andReturn(response()->json(['data' => []]));
        $this->signInAs(User::factory()->create(['rol' => UserRole::CONTADOR]));
        $this->getJson('/api/v1/cobros?cliente_id=1')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        config(['services.internal_api.token' => 'security-service-test']);
        $this->withToken('security-service-test')->getJson('/api/v1/cobros?cliente_id=1')->assertOk();
    }

    public function test_internal_product_fields_require_an_administrative_user(): void
    {
        config(['services.internal_api.token' => 'security-service-test']);
        $this->withToken('security-service-test')->getJson('/api/productos?interno=1')->assertForbidden();

        $user = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $token = app(ApiTokenService::class)->issue($user, request())['token'];
        $this->mock(ProductoController::class)->shouldReceive('index')->once()
            ->andReturn(response()->json(['data' => [['costo' => 12]]]));
        $this->withToken($token)->getJson('/api/productos?interno=1')->assertOk()->assertJsonPath('data.0.costo', 12);
    }

    public function test_service_stock_writes_require_an_explicit_active_audit_account(): void
    {
        config(['services.internal_api.token' => 'security-service-test', 'services.internal_api.user_id' => null]);
        $this->withToken('security-service-test')->postJson('/api/v1/stock/movimientos', [])->assertStatus(503);

        $actor = User::factory()->create();
        config(['services.internal_api.user_id' => $actor->id]);
        $this->mock(StockController::class)->shouldReceive('registrarMovimientosLote')->once()
            ->andReturnUsing(fn (Request $request) => response()->json(['actor' => UsuarioActual::id($request)]));
        $this->withHeader('X-User-Id', '999')->postJson('/api/v1/stock/movimientos', [])
            ->assertOk()->assertJsonPath('actor', $actor->id);

        $actor->update(['estado' => 'inactivo']);
        $this->postJson('/api/v1/stock/movimientos', [])->assertStatus(503);
    }

    public function test_invalid_bearer_never_uses_an_existing_web_session(): void
    {
        $this->signInAs(User::factory()->create());
        $this->withToken('invalid')->postJson('/api/productos', [])->assertUnauthorized();
    }

    public function test_expired_and_replaced_sessions_cannot_write(): void
    {
        $user = User::factory()->create();
        $this->signInAs($user)->withSession(['last_activity' => now()->subHours(3)->timestamp]);
        $this->postJson('/api/productos', [])->assertUnauthorized();
        $this->signInAs($user);
        $user->forceFill(['current_session_id' => 'another-session'])->save();
        $this->postJson('/api/productos', [])->assertUnauthorized();
    }

    public function test_session_writes_enforce_csrf_and_accept_a_matching_token(): void
    {
        $this->app->instance('env', 'local');
        $this->signInAs(User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]))->withSession(['_token' => 'csrf-security-test']);
        $this->postJson('/api/productos', [])->assertStatus(419);
        $this->mock(ProductoController::class)->shouldReceive('store')->once()
            ->andReturn(response()->json(['saved' => true], 201));
        $this->withHeader('X-CSRF-TOKEN', 'csrf-security-test')->postJson('/api/productos', [])->assertCreated();
    }

    public function test_bank_import_accepts_csrf_session_and_rejects_repartidor(): void
    {
        $this->signInAs(User::factory()->create(['rol' => UserRole::REPARTIDOR]));
        $this->postJson('/api/extractos/importar', [])->assertForbidden();
        $this->signInAs(User::factory()->create(['rol' => UserRole::CONTADOR]));
        $this->mock(MovimientoBancarioController::class)->shouldReceive('importar')->once()
            ->andReturn(response()->json(['ok' => true], 201));
        $this->postJson('/api/extractos/importar', [])->assertCreated();
    }

    public function test_identity_helper_rejects_an_unverified_request_attribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set(UsuarioActual::ATTRIBUTO, 1);
        $request->headers->set('X-User-Id', '1');
        try {
            UsuarioActual::id($request);
            $this->fail('An unverified actor was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('usuarios')->count());
    }
}
