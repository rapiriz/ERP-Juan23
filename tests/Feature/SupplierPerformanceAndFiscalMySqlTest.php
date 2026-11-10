<?php

namespace Tests\Feature;

use App\Modules\Facturacion\Contracts\FiscalIssuer;
use App\Modules\Facturacion\Services\FiscalReadiness;
use App\Modules\Login\Models\User;
use App\Modules\Productos\Models\Producto;
use App\Modules\Proveedores\Services\SupplierPerformance;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\UsesG3Database;

class SupplierPerformanceAndFiscalMySqlTest extends TestCase
{
    use UsesG3Database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDatabaseTransaction();
    }

    public function test_supplier_statistics_do_not_treat_unknown_dates_as_late_deliveries(): void
    {
        $actor = User::where('usuario','qa-fixture-admin')->value('id');
        $supplier = DB::table('proveedor')->value('id_proveedor');
        foreach ([[0,0,'completada'],[-4,-1,'completada'],[null,-1,'completada'],[-1,null,'pendiente']] as $index => [$expected,$actual,$state]) {
            $id = DB::table('compra')->insertGetId(['numero_compra'=>'QA-STATS-'.$index, 'id_proveedor'=>$supplier,
                'importe_total'=>100,'saldo_pendiente'=>100,'estado'=>$state,'fecha_compra'=>today()->subDays(10),
                'fecha_entrega_prevista'=>$expected === null ? null : today()->addDays($expected),'id_usuario'=>$actor], 'id_compra');
            if ($actual !== null) DB::table('recepcion')->insert(['id_compra'=>$id,'id_proveedor'=>$supplier,'fecha_recepcion'=>today()->addDays($actual),'id_usuario'=>$actor]);
        }
        $row = app(SupplierPerformance::class)->report(['id_proveedor'=>$supplier])['data'][0];
        $this->assertSame(4,$row['compras']);
        $this->assertSame(2,$row['entregas_evaluables']);
        $this->assertSame(50.0,$row['porcentaje_en_termino']);
        $this->assertSame(1.5,$row['demora_promedio_dias']);
        $this->assertSame(1,$row['sin_fecha_comprometida']);
        $this->assertSame(1,$row['pendientes_vencidas']);
        $this->get('/proveedores/estadisticas')->assertOk()->assertSeeText('Cumplimiento de proveedores');
    }

    public function test_new_purchase_snapshots_expected_delivery_without_backfilling_history(): void
    {
        $supplier = DB::table('proveedor')->value('id_proveedor');
        DB::table('proveedor')->where('id_proveedor',$supplier)->update(['plazo_entrega_dias'=>3]);
        $product = Producto::firstOrFail();
        $this->postJson('/api/compras',['id_proveedor'=>$supplier,'items'=>[['id_producto'=>$product->id_producto,'cantidad'=>1,'precio_unitario'=>'10.50']]])
            ->assertCreated()->assertJsonPath('data.fecha_entrega_prevista',today()->addDays(3)->toDateString());
        DB::table('proveedor')->where('id_proveedor',$supplier)->update(['plazo_entrega_dias'=>9]);
        $this->assertDatabaseHas('compra',['fecha_entrega_prevista'=>today()->addDays(3)->toDateString()]);
    }

    public function test_fiscal_preparation_never_generates_fake_authorization(): void
    {
        config(['fiscal.cuit'=>null,'fiscal.point_of_sale'=>null,'fiscal.certificate_path'=>null,'fiscal.private_key_path'=>null]);
        $status = app(FiscalReadiness::class)->status();
        $this->assertFalse($status['emision_habilitada']);
        $this->assertFalse($status['adaptador_arca_implementado']);
        $this->get('/operaciones/fiscal')->assertOk()->assertSeeText('sin CAE');
        $this->getJson('/api/v1/fiscal/estado')->assertOk()->assertJsonPath('data.emision_habilitada', false);
        config(['services.internal_api.token' => 'fiscal-qa-service', 'services.internal_api.user_id' => User::where('usuario','qa-fixture-admin')->value('id')]);
        $response = app(\App\Services\Integrations\InternalApiClient::class)->get('/api/v1/fiscal/estado', [], 'fiscal-qa-service');
        $this->assertSame(200, $response->status());
        $this->expectException(\DomainException::class);
        app(FiscalIssuer::class)->issue(['total'=>100]);
    }
}
