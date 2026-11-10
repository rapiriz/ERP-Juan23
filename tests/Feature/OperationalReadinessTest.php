<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Login\Models\User;
use App\Modules\Shared\Services\FinancialIntegrityReview;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Integraciones\MockStockApi;
use App\Services\DeploymentReadiness;
use App\Services\SchemaCaseGuard;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_monitor_requires_admin_and_escapes_diagnostics(): void
    {
        $this->get('/operaciones/stock')->assertRedirect('/login');
        $this->signInAs(User::factory()->create(['rol' => 'contador']));
        $this->get('/operaciones/stock')->assertRedirect('/dashboard');
        $this->signInAs(User::factory()->create(['rol' => 'administrativo']));
        DB::table('g4_stock_operations')->insert(['request_key' => (string) Str::uuid(), 'venta_id' => 1,
            'actor_id' => auth()->id(), 'motivo' => 'venta', 'cambios' => '{}', 'estado' => 'pendiente', 'intentos' => 1,
            'ultimo_error' => '<script>alert(1)</script>', 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/operaciones/stock')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_retry_preserves_key_and_never_applies_an_operation_twice(): void
    {
        $this->signInAs(User::factory()->create(['rol' => 'administrativo']));
        config(['ventas.integracion' => 'mock']);
        $stock = app(MockStockApi::class);
        app()->instance(StockGateway::class, $stock);
        $key = (string) Str::uuid();
        $id = DB::table('g4_stock_operations')->insertGetId(['request_key' => $key, 'venta_id' => 1,
            'actor_id' => auth()->id(), 'motivo' => 'venta', 'cambios' => '{"7":-2}', 'estado' => 'pendiente', 'intentos' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/operaciones/stock/'.$id.'/reintentar')->assertRedirect('/operaciones/stock');
        $this->post('/operaciones/stock/'.$id.'/reintentar')->assertRedirect('/operaciones/stock');
        $this->assertDatabaseHas('g4_stock_operations', ['id' => $id, 'request_key' => $key, 'estado' => 'aplicado', 'intentos' => 1]);
        $this->assertCount(1, $stock->avisos());
    }

    public function test_historical_review_reports_unlinked_money_without_writing(): void
    {
        $client = Cliente::factory()->create();
        DB::table('cuenta_corriente_movimientos')->insert(['cliente_id' => $client->id, 'tipo' => 'pago', 'importe' => 50, 'descripcion' => 'Histórico', 'created_at' => now(), 'updated_at' => now()]);
        $before = DB::table('cuenta_corriente_movimientos')->get()->toJson();
        $this->assertEquals(1, app(FinancialIntegrityReview::class)->findings()['asientos_sin_pago']['total']);
        $this->artisan('erp:audit-finanzas --json')->assertExitCode(1);
        $this->assertSame($before, DB::table('cuenta_corriente_movimientos')->get()->toJson());
        $this->assertDatabaseCount('cuenta_corriente_pagos', 0);
    }

    public function test_test_guard_refuses_a_normal_mysql_connection_before_sql(): void
    {
        config(['database.connections.forbidden' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
            'database' => 'business_database', 'username' => 'none', 'password' => 'none']]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Conexión de pruebas rechazada');
        DB::connection('forbidden')->select('select 1');
    }

    public function test_production_doctor_refuses_development_settings_without_sending_mail(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'session.secure' => false, 'mail.default' => 'array']);
        $checks = app(DeploymentReadiness::class)->checks(true);
        $this->assertFalse($checks['debug_desactivado']);
        $this->assertFalse($checks['url_https']);
        $this->assertFalse($checks['cookies_seguras']);
        $this->assertFalse($checks['smtp_configurado']);
        $this->artisan('erp:doctor --production --json')->assertExitCode(1);
    }

    public function test_scheduler_registers_closure_stock_recovery_and_heartbeat(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");
        foreach (['caja:cerrar-vencidas', 'ventas:reenviar-stock', 'erp:scheduler-heartbeat'] as $command) {
            $this->assertStringContainsString($command, $commands);
        }
    }

    public function test_scheduled_commands_close_expired_driver_cash_and_retry_stock_in_test_db(): void
    {
        $driver = User::factory()->create(['rol' => 'repartidor']);
        app(CajaService::class)->abrirCaja($driver->id, 'repartidor', 0);
        DB::table('CAJA')->where('id_usuario', $driver->id)->update(['fecha_hora_vencimiento' => now()->subMinute()]);
        $this->artisan('caja:cerrar-vencidas')->assertExitCode(0);
        $this->assertDatabaseHas('CAJA', ['id_usuario' => $driver->id, 'estado' => 'cerrada', 'cierre_automatico' => 1]);
        config(['ventas.integracion' => 'mock']);
        app()->instance(StockGateway::class, app(MockStockApi::class));
        $id = DB::table('g4_stock_operations')->insertGetId(['request_key' => (string) Str::uuid(), 'venta_id' => 2, 'actor_id' => $driver->id,
            'motivo' => 'venta', 'cambios' => '{"7":-1}', 'estado' => 'pendiente', 'intentos' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('ventas:reenviar-stock --limit=100')->assertExitCode(0);
        $this->assertDatabaseHas('g4_stock_operations', ['id' => $id, 'estado' => 'aplicado', 'intentos' => 1]);
    }

    public function test_schema_inventory_reads_structure_without_altering_rows(): void
    {
        $client = Cliente::factory()->create();
        $before = DB::table('clientes')->get()->toJson();
        $this->artisan('erp:inventario-esquema --json')->assertExitCode(0);
        $this->assertSame($before, DB::table('clientes')->get()->toJson());
    }

    public function test_production_json_does_not_leak_controller_sql_diagnostics(): void
    {
        config(['app.debug' => false]);
        Route::middleware(['api'])->get('/qa-redacted-error', fn () => response()->json(['message' => 'SQLSTATE test diagnostic', 'trace' => 'private'], 500));
        $this->getJson('/qa-redacted-error')->assertStatus(500)->assertExactJson(['message' => 'Server Error'])->assertHeader('X-Request-ID');
    }

    public function test_migration_guard_blocks_incompatible_case_without_altering_data(): void
    {
        $before = DB::table('migrations')->count();
        $guard = \Mockery::mock(SchemaCaseGuard::class);
        $guard->shouldReceive('conflicts')->once()->andReturn([['actual' => 'PRODUCTO', 'expected' => 'producto']]);
        app()->instance(SchemaCaseGuard::class, $guard);
        try {
            Event::dispatch(new MigrationsStarted('up'));
            $this->fail('Debe rechazar un esquema incompatible antes de ejecutar las migraciones.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Nombres de tablas incompatibles', $error->getMessage());
        }
        $this->assertSame($before, DB::table('migrations')->count());
    }
}
