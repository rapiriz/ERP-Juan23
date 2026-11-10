<?php

namespace Tests\Feature;

use App\Modules\Clientes\Models\Cliente;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\Login\Models\User;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Services\Gateways\ExternalFinanceGateway;
use App\Services\Integrations\InternalApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LocalIntegrationContractsTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Cliente $client;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actor = User::factory()->create(['rol' => 'administrativo']);
        $this->client = Cliente::factory()->create(['creado_por' => $this->actor->id]);
        config(['services.internal_api.token' => 'local-contract-token',
            'services.internal_api.user_id' => $this->actor->id]);
    }

    private function sale(int $cents = 10000, string $state = 'confirmada'): int
    {
        return DB::table('venta')->insertGetId([
            'id_cliente' => $this->client->id, 'fecha' => '2026-11-08',
            'total' => $cents / 100, 'descuento_global' => 0,
            'numFactura' => 'CONTRACT-'.bin2hex(random_bytes(4)),
            'estado' => $state, 'observaciones' => 'Prueba', 'id_usuario' => $this->actor->id,
        ], 'id_venta');
    }

    private function payment(int $cents, string $type = 'pago'): void
    {
        DB::table('cuenta_corriente_movimientos')->insert([
            'cliente_id' => $this->client->id, 'venta_id' => null, 'tipo' => $type,
            'descripcion' => 'Movimiento aplicado', 'importe' => $cents / 100,
            'referencia_externa' => 'Efectivo', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_financial_reads_require_authentication(): void
    {
        foreach (['clientes/'.$this->client->id.'/saldo', 'clientes/deudores', 'facturas/pendientes', 'cobros?cliente_id='.$this->client->id] as $path) {
            $this->getJson('/api/v1/finanzas/'.$path)->assertUnauthorized();
        }
    }

    public function test_balance_uses_real_entries_and_preserves_credit_sign(): void
    {
        $sale = $this->sale();
        DB::table('cuenta_corriente_movimientos')->insert([
            'cliente_id' => $this->client->id, 'venta_id' => $sale, 'tipo' => 'venta',
            'descripcion' => 'Venta', 'importe' => -100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->payment(3575);
        $this->withToken('local-contract-token')->getJson('/api/v1/finanzas/clientes/'.$this->client->id.'/saldo')
            ->assertOk()->assertJsonPath('data.saldo', 64.25)->assertJsonPath('data.saldo_contable', -64.25);
        $this->payment(10000, 'nota_credito');
        $this->getJson('/api/v1/finanzas/clientes/'.$this->client->id.'/saldo')
            ->assertOk()->assertJsonPath('data.saldo', 0)->assertJsonPath('data.saldo_a_favor', 35.75);
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 3);
    }

    public function test_historical_sales_without_entries_are_counted_once_and_unknown_due_dates_remain_null(): void
    {
        $this->sale(10050);
        $this->sale(4000, 'cancelada');
        $this->payment(2550);
        $this->withToken('local-contract-token')->getJson('/api/v1/finanzas/facturas/pendientes')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.importe_pendiente', 75)
            ->assertJsonPath('data.0.vencimiento', null);
        $this->getJson('/api/v1/finanzas/clientes/deudores')
            ->assertOk()->assertJsonPath('data.0.saldo', 75);
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
    }

    public function test_g1_reads_all_pages_of_applied_payments_over_internal_routes(): void
    {
        for ($i = 0; $i < 123; $i++) {
            $this->payment(100);
        }
        config(['services.billing.transport' => 'internal', 'services.billing.token' => 'local-contract-token',
            'services.billing.payments_path' => '/api/v1/finanzas/cobros']);
        $payments = app(ExternalFinanceGateway::class)->clientPayments($this->client->id);
        $this->assertCount(123, $payments);
        $this->assertTrue($payments[0]['confirmacion_individual']);
        Http::assertNothingSent();
    }

    public function test_invoice_specific_payments_and_unallocated_credits_use_distinct_allocation_rules(): void
    {
        $first = $this->sale(20000);
        $second = $this->sale(10000);
        $this->payment(5000);
        DB::table('cuenta_corriente_movimientos')->update(['referencia_externa' => 'rendicion_cobro:55']);
        $this->payment(5000, 'nota_credito');

        $account = app(FinancialReadService::class)
            ->account($this->client->id, collectionInvoices: collect([55 => $second]));
        $invoices = $account['invoices']->keyBy('id');
        $this->assertEquals(150, $invoices[$first]['importe_pendiente']);
        $this->assertEquals(50, $invoices[$second]['importe_pendiente']);
        $this->assertEquals(200, $account['balance']['saldo']);
        $this->assertCount(1, $account['payments']);
        $this->assertSame('Rendición aplicada', $account['payments'][0]['medio_pago']);
    }

    public function test_internal_sales_client_uses_the_registered_real_g1_api(): void
    {
        config(['ventas.integracion' => 'internal']);
        $client = app(ClienteGateway::class)->buscar($this->client->id);
        $this->assertSame($this->client->dni_cuit, $client['cuit']);
        Http::assertNothingSent();
    }

    public function test_internal_request_does_not_borrow_the_browser_identity_and_restores_it_after_denial(): void
    {
        $this->signInAs($this->actor)->withSession(['marker' => 'outside']);
        $before = app('request');
        $response = app(InternalApiClient::class)->get('/api/v1/clientes', [], 'invalid');
        $this->assertSame(401, $response->status());
        $this->assertSame($before, app('request'));
        $this->assertSame($this->actor->id, Auth::id());
        $this->assertSame('outside', session('marker'));
    }

    public function test_internal_transport_rejects_unlisted_application_routes(): void
    {
        $this->expectException(HttpException::class);
        app(InternalApiClient::class)->post('/api/productos', ['nombre' => 'Forbidden'], 'local-contract-token');
    }
}
