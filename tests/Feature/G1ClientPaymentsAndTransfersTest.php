<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\Login\Models\User;
use App\Modules\NotificacionesTransferencia\Models\TransferNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class G1ClientPaymentsAndTransfersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cliente $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->admin = User::factory()->create(['rol' => 'administrativo']);
        $this->client = Cliente::factory()->create(['creado_por' => $this->admin->id]);
        $this->signInAs($this->admin);
        config(['services.internal_api.token' => 'fixture-service', 'services.internal_api.user_id' => $this->admin->id,
            'services.balances.transport' => 'internal', 'services.billing.transport' => 'internal',
            'services.balances.client_path' => '/api/v1/finanzas/clientes/{id}/saldo',
            'services.billing.payments_path' => '/api/v1/finanzas/cobros', 'services.sales.url' => null, 'services.rendiciones.url' => null]);
        $sale = DB::table('venta')->insertGetId(['id_cliente' => $this->client->id, 'fecha' => today(), 'total' => 100.50,
            'descuento_global' => 0, 'numFactura' => 'PRUEBA-PAGO', 'estado' => 'confirmada', 'observaciones' => 'Prueba', 'id_usuario' => $this->admin->id], 'id_venta');
        DB::table('cuenta_corriente_movimientos')->insert(['cliente_id' => $this->client->id, 'venta_id' => $sale,
            'tipo' => 'venta', 'descripcion' => 'Venta', 'importe' => -100.50, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function payment(array $changes = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'monto' => '25.25', 'medio_pago' => 'efectivo', 'comprobante' => 'REF-1'], ...$changes];
    }

    private function notice(array $changes = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'cliente_id' => $this->client->id, 'fecha' => today()->toDateString(),
            'monto' => '25.25', 'referencia' => 'BANCO-123', 'comprobante' => UploadedFile::fake()->createWithContent('recibo.pdf', "%PDF-1.4\nComprobante de prueba\n%%EOF")], ...$changes];
    }

    public function test_partial_payment_updates_cash_debt_history_and_audit_once(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $data = $this->payment();
        $uri = '/api/v1/finanzas/clientes/'.$this->client->id.'/pagos';
        $this->postJson($uri, $data)->assertCreated()->assertJsonPath('data.saldo_restante', 75.25);
        $this->postJson($uri, $data)->assertOk()->assertJsonPath('data.repetido', true);
        $this->assertDatabaseCount('cuenta_corriente_pagos', 1);
        $this->assertDatabaseCount('cuenta_corriente_pago_eventos', 1);
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 1);
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 2);
        $this->assertDatabaseHas('cuenta_corriente_pagos', ['usuario_id' => $this->admin->id, 'saldo_anterior' => 100.50, 'saldo_restante' => 75.25]);
        $this->get(route('clientes.show', $this->client))->assertOk()->assertSeeText('75,25')->assertSeeText('REF-1')->assertSeeText('Aplicado');
        Http::assertNothingSent();
    }

    public function test_payment_requires_open_cash_and_never_exceeds_debt_or_accepts_fractional_cents(): void
    {
        $uri = '/api/v1/finanzas/clientes/'.$this->client->id.'/pagos';
        $this->postJson($uri, $this->payment())->assertUnprocessable()->assertJsonValidationErrors('caja');
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $this->postJson($uri, $this->payment(['monto' => '100.51']))->assertUnprocessable();
        $this->postJson($uri, $this->payment(['monto' => '1.001']))->assertUnprocessable();
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 0);
        $this->assertDatabaseCount('cuenta_corriente_pagos', 0);
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
    }

    public function test_total_payment_clears_debt_and_reusing_a_key_for_different_amount_is_a_conflict(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $data = $this->payment(['monto' => '100.50']);
        $uri = '/api/v1/finanzas/clientes/'.$this->client->id.'/pagos';
        $this->postJson($uri, $data)->assertCreated()->assertJsonPath('data.saldo_restante', 0);
        $this->postJson($uri, [...$data, 'monto' => '1'])->assertStatus(409);
        $this->assertEquals(0, app(FinancialReadService::class)->account($this->client->id)['balance']['saldo']);
    }

    public function test_failure_after_cash_write_rolls_back_all_financial_effects(): void
    {
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        DB::unprepared("CREATE TRIGGER reject_payment BEFORE INSERT ON cuenta_corriente_pagos BEGIN SELECT RAISE(FAIL, 'isolated failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/v1/finanzas/clientes/'.$this->client->id.'/pagos', $this->payment());
            $this->fail('Se esperaba la falla controlada de la base de prueba.');
        } catch (QueryException) {
            $this->assertDatabaseCount('CAJA_MOVIMIENTO', 0);
            $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
            $this->assertDatabaseCount('cuenta_corriente_pagos', 0);
        }
    }

    public function test_transfer_registers_private_file_and_stays_pending_without_crediting_money(): void
    {
        $data = $this->notice();
        $response = $this->postJson('/api/v1/transferencias', $data)->assertCreated()->assertJsonPath('data.estado', 'pendiente');
        $id = $response->json('data.id');
        $notice = TransferNotification::findOrFail($id);
        Storage::disk('local')->assertExists($notice->archivo);
        $this->assertSame(hash('sha256', "%PDF-1.4\nComprobante de prueba\n%%EOF"), $notice->sha256);
        $this->assertArrayNotHasKey('archivo', $response->json('data'));
        $this->get('/api/v1/transferencias/'.$id.'/comprobante')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
        $this->assertDatabaseCount('CAJA_MOVIMIENTO', 0);
        $this->assertDatabaseCount('g1_transferencia_eventos', 1);
        $this->get('/transferencias')->assertOk()->assertSeeText('BANCO-123');
    }

    public function test_transfer_validates_required_fields_mime_size_and_dates(): void
    {
        $this->postJson('/api/v1/transferencias', [])->assertUnprocessable()->assertJsonValidationErrors(['cliente_id', 'monto', 'fecha', 'referencia', 'comprobante']);
        $path = tempnam(sys_get_temp_dir(), 'erp-receipt-');
        try {
            file_put_contents($path, '<html><script>alert(1)</script></html>');
            $this->postJson('/api/v1/transferencias', $this->notice(['comprobante' => new UploadedFile($path, 'ataque.pdf', null, null, true)]))->assertUnprocessable();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->postJson('/api/v1/transferencias', $this->notice(['comprobante' => UploadedFile::fake()->create('grande.pdf', 5121, 'application/pdf')]))->assertUnprocessable();
        $this->postJson('/api/v1/transferencias', $this->notice(['fecha' => today()->addDay()->toDateString()]))->assertUnprocessable();
        $this->assertDatabaseCount('g1_transferencias', 0);
    }

    public function test_transfer_retries_do_not_duplicate_and_review_does_not_apply_payment_automatically(): void
    {
        $data = $this->notice();
        $id = $this->postJson('/api/v1/transferencias', $data)->assertCreated()->json('data.id');
        $data['comprobante'] = $this->notice()['comprobante'];
        $this->postJson('/api/v1/transferencias', $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->patchJson('/api/v1/transferencias/'.$id.'/revision', ['estado' => 'validada', 'nota' => 'Comprobante revisado'])->assertOk();
        $this->getJson('/api/v1/transferencias')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
        $this->assertDatabaseCount('g1_transferencia_eventos', 2);
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $payment = $this->payment(['medio_pago' => 'transferencia', 'transferencia_id' => $id]);
        $uri = '/api/v1/finanzas/clientes/'.$this->client->id.'/pagos';
        $this->postJson($uri, $payment)->assertCreated();
        $this->postJson($uri, [...$payment, 'request_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertDatabaseCount('cuenta_corriente_pagos', 1);
    }

    public function test_non_admin_cannot_register_payments_transfers_or_review_but_counter_can_read(): void
    {
        $this->signInAs(User::factory()->create(['rol' => 'contador']));
        $this->postJson('/api/v1/transferencias', $this->notice())->assertForbidden();
        $this->postJson('/api/v1/finanzas/clientes/'.$this->client->id.'/pagos', $this->payment())->assertForbidden();
        $this->get('/transferencias')->assertOk();
        $this->signInAs(User::factory()->create(['rol' => 'repartidor']));
        $this->getJson('/api/v1/transferencias')->assertForbidden();
    }
}
