<?php

namespace Tests\Feature;

use App\Modules\Clientes\Models\Cliente;
use App\Modules\Login\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class G1PaymentHistoryAndRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_history_reads_all_pages_and_distinguishes_declared_approved_and_applied(): void
    {
        $admin = User::factory()->create(['rol' => 'administrativo']);
        $client = Cliente::factory()->create(['creado_por' => $admin->id]);
        config(['services.sales.url' => null, 'services.billing.url' => 'https://billing.test', 'services.billing.token' => 'fixture',
            'services.billing.payments_path' => '/payments', 'services.balances.url' => null,
            'services.rendiciones.url' => 'https://collections.test', 'services.rendiciones.token' => 'fixture', 'services.rendiciones.path' => '/list']);
        Http::preventStrayRequests();
        Http::fake(['billing.test/*' => function ($request) use ($client) {
            $page = (int) $request['page'];

            return Http::response(['data' => collect(range(($page - 1) * 11 + 1, $page * 11))->map(fn ($id) => [
                'id' => $id, 'cliente_id' => $client->id, 'fecha' => '2026-11-09', 'monto_total' => 1,
                'medio_pago' => 'efectivo', 'confirmacion_individual' => true, 'rendicion_cobro_id' => $id === 22 ? 3 : null,
                'comprobante_nro' => 'PAGO-'.$id])->all(), 'meta' => ['current_page' => $page, 'last_page' => 2, 'total' => 22]]);
        }, 'collections.test/*' => function ($request) use ($client) {
            $page = (int) $request['page'];
            $rows = collect($page === 1 ? [1, 2] : [3])->map(fn ($id) => [
                'id_rendicion' => $id, 'fecha' => '2026-11-09', 'estado' => $id === 1 ? 'pendiente' : 'aprobada',
                'cobros' => [['id_rendicion_cobro' => $id, 'id_rendicion' => $id, 'id_cliente' => $client->id,
                    'id_factura' => $id, 'monto' => 1, 'medio_pago' => 'efectivo', 'fecha_registro' => '2026-11-09']]])->all();

            return Http::response(['data' => $rows, 'meta' => ['current_page' => $page, 'last_page' => 2, 'total' => 3]]);
        }]);
        $this->signInAs($admin)->get(route('clientes.show', $client))->assertOk()->assertSeeText('PAGO-22')
            ->assertSeeText('Declarado · pendiente de aprobación')->assertSeeText('Aprobada · sin aplicación informada')->assertSeeText('Aplicado')
            ->assertViewHas('cobros', fn ($rows) => count($rows) === 22)->assertViewHas('cobrosRendidos', fn ($snapshot) => count($snapshot['rows']) === 3 && collect($snapshot['rows'])->firstWhere('id', 3)['aplicacion'] === 'Aplicado');
    }

    public function test_declared_collection_api_cannot_be_mistaken_for_applied_payment_history(): void
    {
        $admin = User::factory()->create(['rol' => 'administrativo']);
        $client = Cliente::factory()->create(['creado_por' => $admin->id]);
        config(['services.sales.url' => null, 'services.billing.url' => 'https://billing.test', 'services.billing.token' => 'fixture',
            'services.balances.url' => null, 'services.rendiciones.url' => null]);
        Http::preventStrayRequests();
        Http::fake(['billing.test/*' => Http::response(['data' => [['id' => 1, 'cliente_id' => $client->id,
            'fecha' => '2026-11-09', 'monto_total' => 100, 'medio_pago' => 'efectivo', 'confirmacion_individual' => null]]])]);
        $this->signInAs($admin)->get(route('clientes.show', $client))->assertOk()->assertViewHas('cobrosDisponibles', false)->assertViewHas('cobros', []);
    }

    public function test_out_of_stock_product_explains_absence_of_available_alternatives(): void
    {
        config(['catalogo.transport' => 'http', 'services.catalog.url' => 'https://catalog.test']);
        Http::preventStrayRequests();
        $product = ['id_producto' => 1, 'codigo' => 'AGOTADO', 'nombre' => 'Producto agotado', 'estado' => 'activo',
            'stock_disponible' => 0, 'stock_minimo' => 2, 'id_categoria' => 1, 'categoria_nombre' => 'Alimentos'];
        Http::fake(['catalog.test/api/v1/productos/1' => Http::response(['data' => $product]),
            'catalog.test/api/v1/productos*' => Http::response(['data' => [$product]])]);
        $this->get('/catalogo/1')->assertOk()->assertSeeText('Sin alternativas disponibles por ahora')->assertSeeText('Explorar todos los productos');
    }

    public function test_recommendation_failure_is_distinguished_from_no_alternatives(): void
    {
        config(['catalogo.transport' => 'http', 'services.catalog.url' => 'https://catalog.test']);
        Http::preventStrayRequests();
        Http::fake(['catalog.test/api/v1/productos/1' => Http::response(['data' => ['id_producto' => 1, 'codigo' => 'AGOTADO', 'nombre' => 'Producto agotado',
            'estado' => 'activo', 'stock_disponible' => 0, 'stock_minimo' => 2]]), 'catalog.test/api/v1/productos*' => Http::response([], 503)]);
        $this->get('/catalogo/1')->assertOk()->assertSeeText('No pudimos consultar las alternativas')->assertDontSeeText('Sin alternativas disponibles por ahora');
    }
}
