<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            $table->decimal('descuento_global', 10, 2)->default(0);
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
        $respuesta = $this->postJson('/saldo/472891/movimientos', [
            'tipo' => 'pago',
            'metodo_pago' => 'Efectivo',
            'monto' => 5000,
        ])
            ->assertCreated()
            ->assertJsonPath('saldo', -10000)
            ->assertJsonPath('movimiento.monto', 5000)
            ->assertJsonPath('movimiento.referencia_externa', 'Efectivo');

        $fechaMovimiento = $respuesta->json('movimiento.fecha');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fechaMovimiento);
        $this->assertSame(
            $fechaMovimiento,
            DB::table('cuenta_corriente_movimientos')->value('created_at')
        );

        $this->assertDatabaseHas('cuenta_corriente_movimientos', [
            'cliente_id' => 472891,
            'tipo' => 'pago',
            'importe' => 5000,
            'referencia_externa' => 'Efectivo',
        ]);

        $this->postJson('/saldo/472891/movimientos', [
            'tipo' => 'pago',
            'metodo_pago' => 'Transferencia',
            'monto' => 10000.01,
        ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('cuenta_corriente_movimientos', 1);
    }

    public function test_credit_note_adds_positive_balance_without_payment_method(): void
    {
        $this->postJson('/saldo/915736/movimientos', [
            'tipo' => 'nota_credito',
            'monto' => 2500,
        ])
            ->assertCreated()
            ->assertJsonPath('saldo', 2500)
            ->assertJsonPath('movimiento.descripcion', 'Nota de crédito')
            ->assertJsonPath('movimiento.referencia_externa', null);

        $this->assertDatabaseHas('cuenta_corriente_movimientos', [
            'cliente_id' => 915736,
            'venta_id' => null,
            'tipo' => 'nota_credito',
            'descripcion' => 'Nota de crédito',
            'importe' => 2500,
            'referencia_externa' => null,
        ]);
    }

    public function test_sale_is_saved_and_decreases_the_selected_customer_balance(): void
    {
        $this->postJson('/ventas', [
            'cliente_id' => 915736,
            'lista' => 'mayorista',
            'observaciones' => 'Entregar el viernes por la mañana.',
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
            'observaciones' => 'Entregar el viernes por la mañana.',
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

    public function test_sale_applies_global_discount_after_per_item_discounts_and_saves_final_total(): void
    {
        $this->postJson('/ventas', [
            'cliente_id' => 915736,
            'lista' => 'mayorista',
            'descuento_global' => ['modo' => 'porcentaje', 'valor' => 10],
            'items' => [
                ['id' => 1, 'cantidad' => 2, 'descuento' => 200],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('descuento_global', 400)
            ->assertJsonPath('total', 3600);

        $this->assertDatabaseHas('venta', [
            'id_venta' => 1,
            'descuento_global' => 400,
            'total' => 3600,
        ]);
        $this->assertDatabaseHas('detalle_venta', [
            'id_venta' => 1,
            'precio_unitario' => 2200,
            'cantidad' => 2,
            'descuento' => 200,
            'subtotal' => 4000,
        ]);
        $this->assertDatabaseHas('cuenta_corriente_movimientos', [
            'venta_id' => 1,
            'importe' => -3600,
        ]);
    }

    public function test_sale_rejects_global_percentage_above_one_hundred(): void
    {
        $this->postJson('/ventas', [
            'cliente_id' => 915736,
            'lista' => 'mayorista',
            'descuento_global' => ['modo' => 'porcentaje', 'valor' => 101],
            'items' => [
                ['id' => 1, 'cantidad' => 1, 'descuento' => 0],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descuento_global.valor');

        $this->assertDatabaseCount('venta', 0);
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
