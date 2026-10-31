<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromocionesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_porcentaje_se_guarda_como_precio_final_y_se_recupera_al_editar(): void
    {
        $datos = [
            'nombre' => 'Promo Coca-Cola',
            'descripcion' => 'Descuento de prueba',
            'vigencia_desde' => '2026-10-01',
            'vigencia_hasta' => '2026-12-31',
            'tipo_descuento' => 'porcentaje',
            'valor' => 25,
            'productos' => [
                ['id_producto' => 1, 'cantidad' => 2],
            ],
        ];

        $this->postJson('/api/promociones', $datos)->assertCreated();
        $id = DB::table('promocion')->value('id_promocion');

        $this->assertDatabaseHas('promocion', [
            'id_promocion' => $id,
            'total' => 3750,
            'tipo_descuento' => 'porcentaje',
            'valor_descuento' => 25,
        ]);
        $this->assertTrue(Schema::hasColumn('promocion', 'tipo_descuento'));
        $this->assertTrue(Schema::hasColumn('promocion', 'valor_descuento'));

        $this->getJson("/api/promociones/{$id}")
            ->assertOk()
            ->assertJsonPath('tipo_descuento', 'porcentaje')
            ->assertJsonPath('valor', 25);

        $datos['valor'] = 10;
        $this->putJson("/api/promociones/{$id}", $datos)->assertOk();

        $this->assertDatabaseHas('promocion', [
            'id_promocion' => $id,
            'total' => 4500,
            'tipo_descuento' => 'porcentaje',
            'valor_descuento' => 10,
        ]);
    }

    public function test_get_promociones_activas_devuelve_solo_promociones_activas_y_vigentes(): void
    {
        $hoy = today()->toDateString();
        $promociones = [
            [
                'nombre' => 'Promo activa vigente',
                'vigencia_desde' => $hoy,
                'vigencia_hasta' => $hoy,
                'estado' => true,
            ],
            [
                'nombre' => 'Promo inactiva',
                'vigencia_desde' => $hoy,
                'vigencia_hasta' => $hoy,
                'estado' => false,
            ],
            [
                'nombre' => 'Promo futura',
                'vigencia_desde' => today()->addDay()->toDateString(),
                'vigencia_hasta' => today()->addDays(2)->toDateString(),
                'estado' => true,
            ],
            [
                'nombre' => 'Promo vencida',
                'vigencia_desde' => today()->subDays(2)->toDateString(),
                'vigencia_hasta' => today()->subDay()->toDateString(),
                'estado' => true,
            ],
        ];

        foreach ($promociones as $promocion) {
            DB::table('promocion')->insert([
                ...$promocion,
                'descripcion' => '',
                'total' => 100,
                'tipo_descuento' => 'monto_fijo',
                'valor_descuento' => 10,
            ]);
        }

        $this->getJson('/api/promociones/activas')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'Promo activa vigente')
            ->assertJsonPath('0.estado', 'activa');
    }

    public function test_monto_fijo_se_guarda_como_precio_final(): void
    {
        $this->postJson('/api/promociones', [
            'nombre' => 'Promo fija',
            'vigencia_desde' => '2026-10-01',
            'vigencia_hasta' => '2026-12-31',
            'tipo_descuento' => 'monto_fijo',
            'valor' => 300,
            'productos' => [
                ['id_producto' => 1, 'cantidad' => 1],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('promocion', [
            'total' => 2200,
            'tipo_descuento' => 'monto_fijo',
            'valor_descuento' => 300,
        ]);
    }

    public function test_monto_fijo_no_puede_superar_el_total_de_productos_pero_puede_igualarlo(): void
    {
        $datos = [
            'nombre' => 'Promo total',
            'vigencia_desde' => '2026-10-01',
            'vigencia_hasta' => '2026-12-31',
            'tipo_descuento' => 'monto_fijo',
            'valor' => 2500.01,
            'productos' => [
                ['id_producto' => 1, 'cantidad' => 1],
            ],
        ];

        $this->postJson('/api/promociones', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valor');

        $this->assertDatabaseCount('promocion', 0);

        $datos['valor'] = 2500;
        $this->postJson('/api/promociones', $datos)
            ->assertCreated();

        $this->assertDatabaseHas('promocion', [
            'total' => 0,
            'valor_descuento' => 2500,
        ]);
    }

    public function test_porcentaje_no_puede_superar_100_pero_puede_ser_igual(): void
    {
        $datos = [
            'nombre' => 'Promo porcentual',
            'vigencia_desde' => '2026-10-01',
            'vigencia_hasta' => '2026-12-31',
            'tipo_descuento' => 'porcentaje',
            'valor' => 100.01,
            'productos' => [
                ['id_producto' => 1, 'cantidad' => 1],
            ],
        ];

        $this->postJson('/api/promociones', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('valor');

        $this->assertDatabaseCount('promocion', 0);

        $datos['valor'] = 100;
        $this->postJson('/api/promociones', $datos)
            ->assertCreated();

        $this->assertDatabaseHas('promocion', [
            'total' => 0,
            'tipo_descuento' => 'porcentaje',
            'valor_descuento' => 100,
        ]);
    }
}
