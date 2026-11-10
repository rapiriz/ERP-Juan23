<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Login\Models\User;
use App\Modules\Reportes\Services\ReportService;
use App\Services\Gateways\ExternalFinanceGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class G1OperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cliente $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(12, 0));
        Http::preventStrayRequests();
        $this->admin = User::factory()->create(['rol' => 'administrativo']);
        $this->client = Cliente::factory()->create(['creado_por' => $this->admin->id]);
        config(['catalogo.transport' => 'http', 'services.catalog.url' => 'https://products.test',
            'services.sales.url' => 'https://sales.test', 'services.sales.token' => 'fixture', 'services.sales.ranking_path' => null,
            'services.sales.buyers_path' => null, 'services.cash.url' => 'https://cash.test', 'services.cash.token' => 'fixture',
            'services.billing.url' => 'https://finance.test', 'services.billing.token' => 'fixture',
            'services.balances.url' => 'https://finance.test', 'services.balances.token' => 'fixture',
            'services.billing.invoices_path' => '/invoices', 'services.balances.debtors_path' => '/debtors']);
        $this->signInAs($this->admin);
    }

    private function sale(int $id, string $state, string $amount = '100.00'): array
    {
        return ['id' => $id, 'fecha' => '2026-11-09', 'estado' => ['valor' => $state],
            'numero_comprobante' => 'F-'.$id, 'total' => $amount, 'descuento_global' => 0,
            'cliente' => ['id' => $this->client->id, 'nombre' => 'Cliente real'],
            'detalles' => [['id_producto' => 1, 'cantidad' => 1, 'subtotal' => $amount, 'producto' => 'Producto']]];
    }

    private function product(int $id, int $stock, int $minimum, string $state = 'activo'): array
    {
        return ['id_producto' => $id, 'codigo' => 'SKU-'.$id, 'nombre' => '=HYPERLINK("https://example.test")',
            'descripcion' => 'Producto', 'estado' => $state, 'stock_disponible' => $stock, 'stock_minimo' => $minimum];
    }

    public function test_daily_sales_read_all_pages_exclude_pending_and_cancelled_and_sum_cents(): void
    {
        Http::fake(['sales.test/*' => function ($request) {
            $page = (int) $request['page'];

            return Http::response(['data' => $page === 1 ? [$this->sale(1, 'confirmada', '150.50'), $this->sale(2, 'pendiente')]
                : [$this->sale(3, 'facturada', '50.25'), $this->sale(4, 'cancelada')],
                'meta' => ['current_page' => $page, 'last_page' => 2, 'total' => 4]]);
        }]);
        $this->getJson('/api/v1/reportes/ventas-diarias?fecha=2026-11-09')->assertOk()
            ->assertJsonCount(2, 'data.rows')->assertJsonPath('data.summary.Operaciones', 2)
            ->assertJsonPath('data.summary.Total vendido', '$ 200,75')->assertJsonPath('data.summary.En estado facturada', '$ 50,25');
        $this->get('/reportes/ventas-diarias?fecha=2026-11-09')->assertOk()->assertSeeText('200,75')->assertDontSeeText('F-2');
    }

    public function test_daily_sales_empty_dates_and_invalid_dates_have_explicit_states(): void
    {
        Http::fake(['sales.test/*' => Http::response(['data' => []])]);
        $this->get('/reportes/ventas-diarias?fecha=2025-01-01')->assertOk()->assertSeeText('No hay registros');
        $this->getJson('/api/v1/reportes/ventas-diarias?fecha=2026-02-30')->assertUnprocessable();
    }

    public function test_daily_cash_reads_the_actual_g2_shape_and_verifies_totals(): void
    {
        $cash = ['fecha' => '2026-11-09', 'ingresos' => 100.10, 'egresos' => 20.05, 'saldo_final' => 80.05,
            'cantidad_movimientos' => 2, 'movimientos' => [
                ['id_movimiento_caja' => 1, 'fecha' => '2026-11-09', 'tipo' => 'cobro', 'concepto' => 'Cobro', 'medio_pago' => 'efectivo', 'sentido' => 'ingreso', 'monto' => 100.10],
                ['id_movimiento_caja' => 2, 'fecha' => '2026-11-09', 'tipo' => 'egreso', 'concepto' => 'Gasto', 'medio_pago' => 'efectivo', 'sentido' => 'egreso', 'monto' => 20.05]]];
        Http::fake(['cash.test/*' => Http::response($cash)]);
        $this->getJson('/api/v1/reportes/caja-diaria?fecha=2026-11-09')->assertOk()->assertJsonPath('data.summary.Neto del día', '$ 80,05');
        $cash['ingresos'] = 999;
        Http::swap((new Factory)->preventStrayRequests());
        Http::fake(['cash.test/*' => Http::response($cash)]);
        $this->get('/reportes/caja-diaria?fecha=2026-11-09')->assertOk()->assertSeeText('Sin datos disponibles')->assertDontSeeText('$ 999');
    }

    public function test_low_stock_uses_minimums_order_and_active_state(): void
    {
        Http::fake(['products.test/*' => Http::response(['data' => [$this->product(1, 0, 2), $this->product(2, 3, 4),
            $this->product(3, 4, 4), $this->product(4, 5, 2), $this->product(5, 0, 3, 'inactivo')]])]);
        $response = $this->getJson('/api/v1/reportes/stock-bajo?orden=desc')->assertOk()->assertJsonCount(3, 'data.rows');
        $this->assertSame(['SKU-3', 'SKU-2', 'SKU-1'], array_column($response->json('data.rows'), 0));
        $response->assertJsonPath('data.rows.2.5', 'Agotado');
    }

    public function test_missing_stock_is_an_error_not_an_out_of_stock_product(): void
    {
        $product = $this->product(1, 0, 2);
        unset($product['stock_disponible']);
        Http::fake(['products.test/*' => Http::response(['data' => [$product]])]);
        $this->getJson('/api/v1/reportes/stock-bajo')->assertStatus(502);
    }

    public function test_due_windows_keep_unknown_dates_separate_and_include_overdue(): void
    {
        $dates = ['2026-11-08', '2026-11-16', '2026-11-17', '2026-11-24', '2026-12-09', '2026-12-10', null];
        Http::fake(['finance.test/*' => Http::response(['data' => collect($dates)->map(fn ($date, $id) => [
            'id' => $id + 1, 'cliente_id' => $this->client->id, 'numero' => 'V-'.$id,
            'vencimiento' => $date, 'importe_pendiente' => 10])->all()])]);
        foreach ([7 => 3, 15 => 5, 30 => 6] as $days => $count) {
            $this->getJson('/api/v1/reportes/vencimientos?fecha=2026-11-09&dias='.$days)->assertOk()
                ->assertJsonCount($count, 'data.rows')->assertJsonPath('data.summary.Sin fecha informada', 1);
        }
        $this->getJson('/api/v1/reportes/vencimientos?dias=8')->assertUnprocessable();
    }

    public function test_every_promised_report_exports_pdf_and_excel_using_the_complete_dataset(): void
    {
        Http::fake(['sales.test/*' => Http::response(['data' => [$this->sale(1, 'confirmada')]]),
            'products.test/*' => Http::response(['data' => [$this->product(1, 0, 2)]]),
            'finance.test/debtors*' => Http::response(['data' => [['cliente_id' => $this->client->id, 'saldo' => 100]]]),
            'finance.test/invoices*' => Http::response(['data' => [['id' => 1, 'cliente_id' => $this->client->id, 'numero' => 'F-1', 'vencimiento' => null, 'importe_pendiente' => 100]]]),
            'cash.test/*' => Http::response(['fecha' => '2026-11-09', 'ingresos' => 0, 'egresos' => 0, 'saldo_final' => 0, 'cantidad_movimientos' => 0, 'movimientos' => []])]);
        foreach (['daily-sales', 'daily-cash', 'low-stock', 'due-invoices', 'debtors', 'product-ranking', 'client-product-reach'] as $report) {
            foreach (['pdf', 'xlsx'] as $format) {
                $response = $this->get(route('reports.export', ['report' => $report, 'format' => $format, 'fecha' => '2026-11-09', 'producto_id' => 1]))->assertOk();
                $response->assertHeader('content-type', $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                if ($format === 'pdf') {
                    $this->assertStringStartsWith('%PDF-', $response->getContent());
                }
            }
        }
    }

    public function test_excel_preserves_text_and_never_turns_external_names_into_formulas(): void
    {
        Http::fake(['products.test/*' => Http::response(['data' => [$this->product(1, 0, 2)]])]);
        $response = $this->get(route('reports.export', ['report' => 'low-stock', 'format' => 'xlsx']))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'erp-xlsx-');
        try {
            file_put_contents($file, $response->streamedContent());
            $book = IOFactory::load($file);
            $this->assertSame(DataType::TYPE_STRING, $book->getActiveSheet()->getCell('B4')->getDataType());
            $this->assertStringStartsWith('=HYPERLINK', $book->getActiveSheet()->getCell('B4')->getValue());
            $this->assertSame(0, $book->getActiveSheet()->getCell('C4')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($file);
        }
    }

    public function test_operational_reports_and_exports_keep_role_restrictions(): void
    {
        $this->signInAs(User::factory()->create(['rol' => 'repartidor']));
        $this->get('/reportes/stock-bajo')->assertRedirect(route('dashboard'));
        $this->getJson('/api/v1/reportes/stock-bajo')->assertForbidden();
        $this->get(route('reports.export', ['report' => 'low-stock', 'format' => 'xlsx']))->assertRedirect(route('dashboard'));
    }

    public function test_cash_consumer_dispatches_the_real_g2_api_internally(): void
    {
        config(['services.cash.transport' => 'internal', 'services.cash.token' => null,
            'services.internal_api.token' => 'cash-contract', 'services.internal_api.user_id' => $this->admin->id]);
        $cash = app(CajaService::class);
        $cash->abrirCaja($this->admin->id, 'admin', 0);
        $cash->registrarCobro($this->admin->id, 'admin', 5.25, 'Cobro real');
        $this->getJson('/api/v1/reportes/caja-diaria?fecha=2026-11-09')->assertOk()->assertJsonPath('data.summary.Neto del día', '$ 5,25');
        Http::assertNothingSent();
    }

    public function test_dedicated_ranking_and_buyer_contracts_are_read_to_the_last_page(): void
    {
        config(['services.sales.ranking_path' => '/ranking', 'services.sales.buyers_path' => '/buyers/{id}']);
        Http::fake(['sales.test/ranking*' => function ($request) {
            $page = (int) $request['page'];

            return Http::response(['data' => [['producto_id' => $page, 'producto_nombre' => 'Producto '.$page, 'cantidad_vendida' => 1, 'monto_facturado' => 10]],
                'meta' => ['current_page' => $page, 'last_page' => 2, 'total' => 2]]);
        }, 'sales.test/buyers/*' => function ($request) {
            $page = (int) $request['page'];

            return Http::response(['data' => [['cliente_id' => $page]], 'meta' => ['current_page' => $page, 'last_page' => 2, 'total' => 2]]);
        }]);
        $this->assertCount(2, app(ExternalFinanceGateway::class)->productRanking(null, null));
        $this->assertSame([1, 2], app(ReportService::class)->productBuyerIds(1));
        Http::assertSentCount(4);
    }
}
