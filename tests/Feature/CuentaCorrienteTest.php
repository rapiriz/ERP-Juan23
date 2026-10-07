<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class CuentaCorrienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('venta', function (Blueprint $table): void {
            $table->increments('id_venta');
            $table->unsignedInteger('id_cliente');
            $table->date('fecha');
            $table->decimal('total', 10, 0);
            $table->string('numFactura', 50);
            $table->string('estado');
            $table->string('observaciones', 150);
            $table->unsignedInteger('id_usuario');
        });

        Schema::create('detalle_venta', function (Blueprint $table): void {
            $table->increments('id_detalle');
            $table->unsignedInteger('id_venta');
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_promocion')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 10, 0);
            $table->decimal('descuento', 10, 0);
            $table->decimal('subtotal', 10, 0);
        });
    }

    public function test_partial_payment_is_saved_and_cannot_exceed_the_debt(): void
    {
        $this->postJson('/saldo/472891/pagos', ['monto' => 5000])
            ->assertCreated()
            ->assertJsonPath('saldo', -10000)
            ->assertJsonPath('movimiento.monto', 5000);

        $this->assertDatabaseHas('cuenta_corriente_movimientos', [
            'cliente_id' => 472891,
            'tipo' => 'pago',
            'importe' => 5000,
        ]);

        $this->postJson('/saldo/472891/pagos', ['monto' => 10000.01])
            ->assertUnprocessable();

        $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
    }

    public function test_sale_is_saved_and_decreases_the_selected_customer_balance(): void
    {
        $this->postJson('/ventas', [
            'cliente_id' => 915736,
            'lista' => 'mayorista',
            'items' => [
                ['id' => 1, 'cantidad' => 2, 'descuento' => 0],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('venta_id', 1)
            ->assertJsonPath('total', 4400);

        $this->assertDatabaseHas('venta', [
            'id_cliente' => 915736,
            'total' => 4400,
            'id_usuario' => 1,
        ]);
        $this->assertDatabaseHas('detalle_venta', [
            'id_venta' => 1,
            'id_producto' => 1,
            'precio_unitario' => 2200,
            'cantidad' => 2,
        ]);
        $this->assertDatabaseHas('cuenta_corriente_movimientos', [
            'cliente_id' => 915736,
            'venta_id' => 1,
            'tipo' => 'venta',
            'importe' => -4400,
        ]);

        $this->get('/saldo')
            ->assertOk()
            ->assertSee('Venta POS #1')
            ->assertSee('"monto":-4400', false);
    }

    public function test_sale_rejects_clients_outside_the_mock_customer_api(): void
    {
        $this->postJson('/ventas', [
            'cliente_id' => 999999,
            'lista' => 'minorista',
            'items' => [
                ['id' => 1, 'cantidad' => 1, 'descuento' => 0],
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('venta', 0);
    }
}
