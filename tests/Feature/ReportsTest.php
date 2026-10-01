<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_sales_report_filters_by_date_and_ignores_cancelled_or_pending_sales(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id]);
        Venta::factory()->create(['cliente_id' => $client->id, 'usuario_id' => $admin->id, 'fecha' => '2026-09-20 10:00:00', 'total' => 1500, 'numero_factura' => 'OK-1']);
        Venta::factory()->create(['cliente_id' => $client->id, 'usuario_id' => $admin->id, 'fecha' => '2026-09-20 12:00:00', 'total' => 900, 'estado' => SaleStatus::CANCELADA, 'numero_factura' => 'NO-1']);
        Venta::factory()->create(['cliente_id' => $client->id, 'usuario_id' => $admin->id, 'fecha' => '2026-09-21 10:00:00', 'total' => 500, 'numero_factura' => 'NO-2']);

        $this->signInAs($admin)->get(route('reports.daily-sales', ['fecha' => '2026-09-20']))
            ->assertOk()
            ->assertSeeText('OK-1')
            ->assertSeeText('$1.500,00')
            ->assertDontSeeText('NO-1')
            ->assertDontSeeText('NO-2');
    }

    public function test_low_stock_report_only_lists_products_below_minimum_in_requested_order(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        Producto::factory()->create(['nombre' => 'Stock dos', 'stock' => 2, 'stock_minimo' => 10]);
        Producto::factory()->create(['nombre' => 'Stock ocho', 'stock' => 8, 'stock_minimo' => 10]);
        Producto::factory()->create(['nombre' => 'Stock justo', 'stock' => 10, 'stock_minimo' => 10]);

        $response = $this->signInAs($admin)->get(route('reports.low-stock', ['orden' => 'asc']))
            ->assertOk()
            ->assertSeeText('Stock dos')
            ->assertSeeText('Stock ocho')
            ->assertDontSeeText('Stock justo');

        $response->assertSeeInOrder(['Stock dos', 'Stock ocho']);
    }

    public function test_client_history_filters_dates_and_displays_products_and_amounts(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id]);
        $product = Producto::factory()->create(['nombre' => 'Yerba 1kg']);
        $visibleSale = Venta::factory()->create(['cliente_id' => $client->id, 'usuario_id' => $admin->id, 'fecha' => '2026-09-15 09:00:00', 'total' => 3200, 'numero_factura' => 'HIST-1']);
        DetalleVenta::query()->create([
            'venta_id' => $visibleSale->id,
            'producto_id' => $product->id,
            'cantidad' => 2,
            'precio_unitario' => 1600,
            'descuento' => 0,
            'subtotal' => 3200,
        ]);
        Venta::factory()->create(['cliente_id' => $client->id, 'usuario_id' => $admin->id, 'fecha' => '2026-08-01 09:00:00', 'numero_factura' => 'OLD-1']);

        $this->signInAs($admin)->get(route('reports.client-history', [
            'cliente_id' => $client->id,
            'desde' => '2026-09-01',
            'hasta' => '2026-09-30',
        ]))->assertOk()
            ->assertSeeText('HIST-1')
            ->assertSeeText('Yerba 1kg')
            ->assertSeeText('$3.200,00')
            ->assertDontSeeText('OLD-1');
    }

    public function test_contador_can_view_reports_and_repartidor_cannot(): void
    {
        $contador = User::factory()->create(['rol' => UserRole::CONTADOR]);
        $repartidor = User::factory()->create(['rol' => UserRole::REPARTIDOR]);

        $this->signInAs($contador)->get(route('reports.index'))->assertOk();
        $this->signInAs($repartidor)->get(route('reports.index'))->assertRedirect(route('dashboard'));
    }

    public function test_repartidor_can_use_operational_sales_and_stock_views_without_exports(): void
    {
        $repartidor = User::factory()->create(['rol' => UserRole::REPARTIDOR]);

        $this->signInAs($repartidor)->get(route('ventas.index'))
            ->assertOk()
            ->assertSeeText('Ventas diarias')
            ->assertDontSeeText('Excel');
        $this->get(route('stock.index'))
            ->assertOk()
            ->assertSeeText('Productos con stock bajo')
            ->assertDontSeeText('Excel');
    }

    public function test_reports_can_be_exported_to_pdf_and_excel(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id]);
        Venta::factory()->create(['cliente_id' => $client->id, 'usuario_id' => $admin->id, 'fecha' => '2026-09-20 10:00:00']);
        $this->signInAs($admin);

        $this->get(route('reports.daily-sales.export', ['format' => 'pdf', 'fecha' => '2026-09-20']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('reports.daily-sales.export', ['format' => 'xlsx', 'fecha' => '2026-09-20']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
