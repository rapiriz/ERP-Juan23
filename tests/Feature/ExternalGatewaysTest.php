<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalGatewaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.sales', [
            'driver' => 'http',
            'url' => 'https://ventas.example.test',
            'token' => 'ventas-token',
        ]);
        config()->set('services.inventory', [
            'driver' => 'http',
            'url' => 'https://inventario.example.test',
            'token' => 'inventario-token',
        ]);
        Http::preventStrayRequests();
    }

    public function test_reports_and_exports_use_partner_json_with_filters_and_bearer_tokens(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id]);
        $sales = json_decode(file_get_contents(base_path('docs/contracts/ventas.json')), true);
        $stock = json_decode(file_get_contents(base_path('docs/contracts/stock-bajo.json')), true);
        Http::fake([
            'ventas.example.test/api/v1/ventas*' => Http::response($sales),
            'inventario.example.test/api/v1/productos/stock-bajo*' => Http::response($stock),
        ]);

        $this->signInAs($admin)->get(route('reports.daily-sales', ['fecha' => '2026-10-06']))
            ->assertOk()->assertSeeText('FC-000123')->assertSeeText('$3.200,00');
        $this->get(route('reports.client-history', [
            'cliente_id' => $client->id,
            'desde' => '2026-10-01',
            'hasta' => '2026-10-06',
        ]))->assertOk()->assertSeeText('Yerba 1kg');
        $this->get(route('reports.low-stock', ['orden' => 'desc']))
            ->assertOk()->assertSeeText('PROD-0001');
        $this->get(route('reports.daily-sales.export', ['format' => 'pdf', 'fecha' => '2026-10-06']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        Http::assertSent(fn ($request) => $request->url() === 'https://ventas.example.test/api/v1/ventas?fecha=2026-10-06'
            && $request->hasHeader('Authorization', 'Bearer ventas-token'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'cliente_id='.$client->id)
            && str_contains($request->url(), 'desde=2026-10-01')
            && str_contains($request->url(), 'hasta=2026-10-06'));
        Http::assertSent(fn ($request) => $request->url() === 'https://inventario.example.test/api/v1/productos/stock-bajo?orden=desc'
            && $request->hasHeader('Authorization', 'Bearer inventario-token'));
    }

    public function test_remote_errors_and_malformed_payloads_are_visible(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $this->signInAs($admin);

        Http::fake(['ventas.example.test/*' => Http::response(['message' => 'fallo'], 500)]);
        $this->get(route('reports.daily-sales'))->assertStatus(502);

        Http::fake(['ventas.example.test/*' => Http::response(['data' => [['fecha' => 'invalid']]])]);
        $this->get(route('reports.daily-sales'))->assertStatus(502);

        config()->set('services.sales.token', null);
        $this->get(route('reports.daily-sales'))->assertStatus(503);
    }

    public function test_remote_mode_keeps_client_payment_without_writing_a_local_sale_allocation(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id, 'saldo' => 100]);

        $this->signInAs($admin)->post(route('cobros.store', $client), [
            'fecha' => now()->format('Y-m-d H:i:s'),
            'monto_total' => 40,
            'medio_pago' => 'efectivo',
        ])->assertRedirect(route('clientes.show', $client));

        $this->assertDatabaseHas('clientes', ['id' => $client->id, 'saldo' => 60]);
        $this->assertDatabaseCount('cobros', 1);
        $this->assertDatabaseCount('cobro_ventas', 0);
    }
}
