<?php

namespace Tests\Feature;

use App\Modules\Compras\Models\Compra;
use App\Modules\Login\Models\User;
use App\Modules\Productos\Models\Producto;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\UsesG3Database;

class SupplierPaymentReversalMySqlTest extends TestCase
{
    use UsesG3Database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDatabaseTransaction();
    }

    private function purchase(bool $receive = false): int
    {
        return $this->postJson('/api/compras', ['id_proveedor' => DB::table('proveedor')->value('id_proveedor'),
            'recibir_ahora' => $receive, 'items' => [['id_producto' => Producto::where('codigo', 'QA-FIXTURE-BASE')->value('id_producto'),
                'cantidad' => 10, 'cantidad_recibida' => 5, 'precio_unitario' => 10]]])->assertCreated()->json('data.id_compra');
    }

    private function pay(int $purchase, int $amount = 70): int
    {
        return $this->postJson('/api/compras/'.$purchase.'/pagos', ['monto' => $amount, 'metodo_pago' => 'transferencia'])
            ->assertCreated()->json('data.pago.id_pago');
    }

    public function test_reversal_is_audited_idempotent_and_allows_a_valid_partial_closure(): void
    {
        $id = $this->purchase(true);$payment = $this->pay($id);
        $this->patchJson('/api/compras/'.$id.'/cerrar-parcial')->assertUnprocessable();
        $this->deleteJson('/api/compras/'.$id.'/pagos/'.$payment, ['motivo' => 'Pago cargado por error'])->assertOk()
            ->assertJsonPath('data.repetido', false)->assertJsonPath('data.resumen.total_pagado', 0)
            ->assertJsonPath('data.resumen.saldo_a_pagar', 100);
        $this->assertDatabaseCount('pago_proveedor', 1);
        $this->assertDatabaseHas('pago_proveedor', ['id_pago' => $payment, 'importe' => 70, 'anulado_por' => auth()->id()]);
        $this->deleteJson('/api/compras/'.$id.'/pagos/'.$payment)->assertOk()->assertJsonPath('data.repetido', true);
        $this->assertSame(1, DB::table('log_auditoria')->where('tabla_afectada', 'pago_proveedor')->where('id_registro', $payment)->count());
        $this->assertEquals(0, Compra::findOrFail($id)->totalPagado());
        $this->pay($id, 30);
        $this->patchJson('/api/compras/'.$id.'/cerrar-parcial')->assertOk()->assertJsonPath('data.saldo_a_pagar', 20);
        $this->getJson('/api/compras/'.$id)->assertOk()->assertJsonCount(2, 'data.pagos.lista')->assertJsonPath('data.pagos.total_pagado', 30);
    }

    public function test_other_purchase_and_read_only_role_cannot_reverse_a_payment(): void
    {
        $a = $this->purchase();$b = $this->purchase();$payment = $this->pay($a);
        $this->deleteJson('/api/compras/'.$b.'/pagos/'.$payment)->assertNotFound();
        $this->signInAs(User::factory()->create(['rol' => 'contador']));
        $this->deleteJson('/api/compras/'.$a.'/pagos/'.$payment)->assertForbidden();
        $this->assertDatabaseHas('pago_proveedor', ['id_pago' => $payment, 'anulado_en' => null]);
    }

    public function test_audit_failure_rolls_back_the_annulment(): void
    {
        $id = $this->purchase();$payment = $this->pay($id);
        Event::listen(QueryExecuted::class, function ($query) {
            if (str_contains(strtolower($query->sql), 'insert into `log_auditoria`')) throw new \RuntimeException('Fallo forzado de auditoría');
        });
        $this->deleteJson('/api/compras/'.$id.'/pagos/'.$payment)->assertStatus(500);
        Event::forget(QueryExecuted::class);
        $this->assertDatabaseHas('pago_proveedor', ['id_pago' => $payment, 'importe' => 70, 'anulado_en' => null]);
        $this->assertEquals(70, Compra::findOrFail($id)->totalPagado());
    }

    public function test_invalid_reason_is_422_without_changing_payment(): void
    {
        $id = $this->purchase();$payment = $this->pay($id);
        foreach ([null, ['no'], '  '] as $reason) {
            $this->deleteJson('/api/compras/'.$id.'/pagos/'.$payment, ['motivo' => $reason])->assertUnprocessable();
        }
        $this->assertDatabaseHas('pago_proveedor', ['id_pago' => $payment, 'anulado_en' => null]);
    }
}
