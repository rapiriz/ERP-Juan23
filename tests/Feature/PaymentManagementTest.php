<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_partial_payment_and_balance_is_updated(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id, 'saldo' => 1000]);
        $sale = Venta::factory()->create([
            'cliente_id' => $client->id,
            'usuario_id' => $admin->id,
            'total' => 800,
        ]);

        $this->signInAs($admin)->post(route('cobros.store', $client), [
            'fecha' => now()->format('Y-m-d H:i:s'),
            'monto_total' => 400,
            'medio_pago' => PaymentMethod::TRANSFERENCIA->value,
            'comprobante_nro' => 'TR-100',
            'observaciones' => 'Pago parcial',
        ])->assertRedirect(route('clientes.show', $client))->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', ['id' => $client->id, 'saldo' => 600]);
        $this->assertDatabaseHas('cobros', [
            'cliente_id' => $client->id,
            'usuario_id' => $admin->id,
            'monto_total' => 400,
            'medio_pago' => 'transferencia',
        ]);
        $this->assertDatabaseHas('cobro_ventas', ['venta_id' => $sale->id, 'monto_aplicado' => 400]);
    }

    public function test_payment_must_be_positive_and_cannot_exceed_balance(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id, 'saldo' => 100]);
        $this->signInAs($admin);

        $this->post(route('cobros.store', $client), $this->paymentData(0))
            ->assertSessionHasErrors('monto_total');
        $this->post(route('cobros.store', $client), $this->paymentData(101))
            ->assertSessionHasErrors('monto_total');

        $this->assertDatabaseCount('cobros', 0);
        $this->assertEquals('100.00', $client->fresh()->saldo);
    }

    public function test_zero_balance_client_cannot_receive_payment(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id, 'saldo' => 0]);

        $this->signInAs($admin)->post(route('cobros.store', $client), $this->paymentData(10))
            ->assertSessionHasErrors('monto_total');
    }

    public function test_repartidor_can_view_history_but_cannot_register_payments(): void
    {
        $admin = User::factory()->create(['rol' => UserRole::ADMINISTRATIVO]);
        $repartidor = User::factory()->create(['rol' => UserRole::REPARTIDOR]);
        $client = Cliente::factory()->create(['creado_por' => $admin->id, 'saldo' => 500]);

        $this->signInAs($repartidor);
        $this->get(route('clientes.show', $client))->assertOk()->assertSeeText('Saldo pendiente');
        $this->get(route('cobros.create', $client))->assertRedirect(route('dashboard'));
        $this->post(route('cobros.store', $client), $this->paymentData(100))->assertRedirect(route('dashboard'));
    }

    private function paymentData(float $amount): array
    {
        return [
            'fecha' => now()->format('Y-m-d H:i:s'),
            'monto_total' => $amount,
            'medio_pago' => PaymentMethod::EFECTIVO->value,
        ];
    }
}
