<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\Login\Models\User;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Models\RendicionCobro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class G2FinancialOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $driver;

    protected Cliente $client;

    protected int $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrativo']);
        $this->driver = User::factory()->create(['rol' => 'repartidor']);
        $this->client = Cliente::factory()->create(['creado_por' => $this->admin->id]);
        $this->signInAs($this->admin);
        $this->sale = DB::table('venta')->insertGetId(['id_cliente' => $this->client->id, 'id_usuario' => $this->admin->id, 'fecha' => today(), 'numFactura' => 'TEST-G2', 'total' => 100.50, 'descuento_global' => 0, 'estado' => 'confirmada', 'observaciones' => 'Prueba de integración'], 'id_venta');
        DB::table('cuenta_corriente_movimientos')->insert(['cliente_id' => $this->client->id, 'venta_id' => $this->sale, 'tipo' => 'venta', 'importe' => -100.50, 'descripcion' => 'Venta', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function item(string $medium = 'efectivo', float $amount = 25.25): RendicionCobro
    {
        $rendition = Rendicion::create(['id_repartidor' => $this->driver->id, 'fecha' => now(), 'total_rendido' => $amount, 'estado' => 'pendiente']);

        return RendicionCobro::create(['id_rendicion' => $rendition->id_rendicion, 'id_cliente' => $this->client->id, 'id_factura' => $this->sale, 'monto' => $amount, 'medio_pago' => $medium, 'fecha_registro' => now()]);
    }

    protected function review(RendicionCobro $item, array $data = [])
    {
        return $this->patchJson('/g2/api/rendiciones/'.$item->id_rendicion.'/revisar', ['accion' => 'aprobar', ...$data]);
    }

    public function test_review_without_cash_rolls_back_every_effect(): void
    {
        $item = $this->item();
        $this->review($item)->assertStatus(422);
        $this->assertDatabaseHas('RENDICION', ['id_rendicion' => $item->id_rendicion, 'estado' => 'pendiente']);
        $this->assertDatabaseCount('cobro', 0);
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 0);
        $this->assertDatabaseCount('cuenta_corriente_pagos', 0);
        $this->assertSame(100.5, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
    }

    public function test_partial_review_persists_individual_states_and_retry_is_not_another_payment(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $first = $this->item();
        $second = RendicionCobro::create(['id_rendicion' => $first->id_rendicion, 'id_cliente' => $this->client->id, 'id_factura' => $this->sale, 'monto' => 10, 'medio_pago' => 'transferencia', 'fecha_registro' => now()]);
        $this->review($first, ['cobros_aprobados' => [$first->id_rendicion_cobro]])->assertOk()->assertJsonPath('data.revision_parcial', true);
        $this->assertDatabaseHas('RENDICION_COBRO', ['id_rendicion_cobro' => $first->id_rendicion_cobro, 'estado_revision' => 'aplicado']);
        $this->assertDatabaseHas('RENDICION_COBRO', ['id_rendicion_cobro' => $second->id_rendicion_cobro, 'estado_revision' => 'pendiente']);
        $this->review($first, ['cobros_aprobados' => [$first->id_rendicion_cobro]])->assertOk();
        $this->assertDatabaseCount('cobro', 1);
        $this->review($first, ['cobros_aprobados' => [$second->id_rendicion_cobro]])->assertOk()->assertJsonPath('data.estado_actual', 'aprobada');
        $this->assertDatabaseCount('cobro', 2);
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 2);
        $account = app(FinancialReadService::class)->account($this->client->id);
        $this->assertSame(65.25, $account['balance']['saldo']);
        $this->assertSame(65.25, $account['invoices']->first()['importe_pendiente']);
        $this->assertDatabaseHas('venta', ['id_venta' => $this->sale, 'estado' => 'confirmada']);
    }

    public function test_failure_on_second_item_rolls_back_first_payment_and_review(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $first = $this->item();
        RendicionCobro::create(['id_rendicion' => $first->id_rendicion, 'id_cliente' => $this->client->id, 'id_factura' => $this->sale, 'monto' => 100, 'medio_pago' => 'transferencia', 'fecha_registro' => now()]);
        $this->review($first)->assertStatus(422);
        $this->assertDatabaseCount('cobro', 0);
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 0);
        $this->assertDatabaseCount('cuenta_corriente_pagos', 0);
        $this->assertDatabaseHas('RENDICION', ['id_rendicion' => $first->id_rendicion, 'estado' => 'pendiente']);
    }

    public function test_cancel_compensates_cash_and_debt_once_without_deleting_history(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $item = $this->item();
        $this->review($item)->assertOk();
        $id = $item->fresh()->id_cobro_aplicado;
        $uri = '/g2/api/cobros/'.$id.'/anular';
        $body = ['motivo' => 'Error verificado'];
        $this->postJson($uri, $body)->assertOk()->assertJsonPath('data.saldo_restante', 100.5);
        $this->postJson($uri, $body)->assertOk()->assertJsonPath('data.repetido', true);
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 2);
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 3);
        $account = app(FinancialReadService::class)->account($this->client->id);
        $this->assertSame(100.5, $account['invoices']->first()['importe_pendiente']);
        $this->assertCount(0, $account['payments']);
        $this->assertDatabaseHas('RENDICION_COBRO', ['id_rendicion_cobro' => $item->id_rendicion_cobro, 'estado_revision' => 'anulado']);
        $this->assertDatabaseHas('g2_operacion_eventos', ['dominio' => 'cobros', 'registro' => (string) $id, 'evento' => 'anulado']);
    }

    public function test_cheque_requires_real_data_and_rejection_restores_debt(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $item = $this->item('cheque');
        $this->review($item)->assertStatus(422);
        $this->assertDatabaseCount('cobro', 0);
        $this->post('/cobros/declarados/'.$item->id_rendicion_cobro.'/cheque', ['numero_cheque' => '12345', 'banco_emisor' => 'Banco de prueba', 'fecha_cobro' => today()->toDateString()])->assertRedirect();
        $this->review($item)->assertOk();
        $check = DB::table('CHEQUE')->first();
        $this->patchJson('/g2/api/cheques/'.$check->id_cheque.'/depositar')->assertOk();
        $this->patchJson('/g2/api/cheques/'.$check->id_cheque.'/rechazar', ['motivo' => 'Rechazado por el banco'])->assertOk();
        $this->assertSame(100.5, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
        $this->assertDatabaseHas('CHEQUE', ['id_cheque' => $check->id_cheque, 'estado' => 'rechazado']);
    }

    public function test_foreign_selection_and_non_admin_do_not_apply_money(): void
    {
        $item = $this->item();
        $other = $this->item();
        $this->review($item, ['cobros_aprobados' => [$other->id_rendicion_cobro]])->assertStatus(422);
        $this->signInAs($this->driver);
        $this->review($item)->assertForbidden();
        $this->assertDatabaseCount('cobro', 0);
    }

    public function test_payment_cash_movement_cannot_be_edited_after_application(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $item = $this->item();
        $this->review($item)->assertOk();
        $move = DB::table('CAJA_MOVIMIENTO')->first();
        $result = app(CajaService::class)->modificarMovimiento($move->id_movimiento_caja, 'Cambio inválido', 5, $this->admin->id);
        $this->assertTrue($result['error']);
        $this->assertDatabaseHas('CAJA_MOVIMIENTO', ['id_movimiento_caja' => $move->id_movimiento_caja, 'monto' => 25.25]);
    }

    public function test_legacy_approved_rendition_is_not_liquidated_a_second_time_without_proof(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $item = $this->item();
        DB::table('RENDICION')->where('id_rendicion', $item->id_rendicion)->update(['estado' => 'aprobada']);
        $this->review($item)->assertConflict();
        $this->assertDatabaseCount('cobro', 0);
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 0);
    }

    public function test_mandatory_audit_failure_rolls_back_cash_account_and_collection(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $item = $this->item();
        DB::unprepared("CREATE TRIGGER fail_g2_audit BEFORE INSERT ON g2_operacion_eventos WHEN NEW.evento='aplicado' BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
        $this->review($item)->assertStatus(500)->assertDontSee('SQLSTATE');
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 0);
        $this->assertDatabaseCount('cuenta_corriente_pagos', 0);
        $this->assertDatabaseCount('cobro', 0);
        $this->assertSame(100.5, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
    }

    public function test_legacy_posting_on_pending_header_is_not_duplicated(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $item = $this->item();
        DB::table('cuenta_corriente_movimientos')->insert(['cliente_id' => $this->client->id, 'venta_id' => null, 'tipo' => 'pago', 'importe' => 25.25, 'descripcion' => 'Aplicación histórica',
            'referencia_externa' => 'rendicion_cobro:'.$item->id_rendicion_cobro, 'created_at' => now(), 'updated_at' => now()]);
        $this->review($item)->assertConflict();
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 0);
        $this->assertDatabaseCount('cobro', 0);
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 2);
    }
}
