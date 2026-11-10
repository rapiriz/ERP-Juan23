<?php

namespace Tests\Feature;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Models\Zona;
use App\Modules\Login\Models\User;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class G2DeliveryPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $driver;

    private Cliente $client;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->admin = User::factory()->create(['rol' => 'administrativo']);
        $this->driver = User::factory()->create(['rol' => 'repartidor']);
        $zone = Zona::create(['nombre' => 'Zona de prueba']);
        $this->client = Cliente::factory()->create(['creado_por' => $this->admin->id, 'zona_id' => $zone->id]);
        config(['g2.transport' => 'internal', 'services.internal_api.token' => 'g2-delivery-test', 'services.internal_api.user_id' => $this->admin->id]);
        $this->app->instance(ClienteGateway::class, new class implements ClienteGateway
        {
            public function todos(): array
            {
                return [];
            }

            public function buscar(int $id): ?array
            {
                $c = Cliente::find($id);

                return $c ? ['id' => $id, 'nombre' => $c->nombre, 'zona_id' => $c->zona_id] : null;
            }
        });
        $this->app->instance(ProductoGateway::class, new class implements ProductoGateway
        {
            public function todos(): array
            {
                return [];
            }

            public function buscar(int $id): ?array
            {
                return ['id' => $id, 'nombre' => 'Producto de prueba', 'codigo' => 'G2-TEST'];
            }
        });
        $this->signInAs($this->admin);
    }

    private function sale(?Cliente $client = null): int
    {
        $id = DB::table('venta')->insertGetId(['id_cliente' => ($client ?? $this->client)->id, 'id_usuario' => $this->admin->id, 'fecha' => today(), 'numFactura' => 'TEST-'.bin2hex(random_bytes(4)),
            'total' => 100, 'descuento_global' => 0, 'estado' => 'confirmada', 'observaciones' => 'Prueba'], 'id_venta');
        DB::table('detalle_venta')->insert(['id_venta' => $id, 'id_producto' => 1, 'cantidad' => 2, 'precio_unitario' => 50, 'descuento' => 0, 'subtotal' => 100]);

        return $id;
    }

    private function create(array $ids)
    {
        return $this->postJson('/g2/api/entregas', ['repartidor_id' => $this->driver->id, 'pedidos_ids' => $ids, 'fecha_salida' => today()->toDateString()]);
    }

    public function test_add_remove_restore_dispatch_and_audit_as_owner_driver(): void
    {
        $first = $this->sale();
        $second = $this->sale();
        $created = $this->create([$first])->assertCreated();
        $id = $created->json('data.id_entrega');
        $this->assertDatabaseHas('venta', ['id_venta' => $first, 'estado' => 'preparada']);
        $this->signInAs($this->driver);
        $this->putJson('/g2/api/entregas/'.$id.'/pedidos', ['pedidos_ids' => [$second]])->assertOk();
        $this->assertDatabaseHas('venta', ['id_venta' => $first, 'estado' => 'confirmada']);
        $this->assertDatabaseHas('venta', ['id_venta' => $second, 'estado' => 'preparada']);
        $this->assertDatabaseHas('remito', ['id_entrega' => $id, 'id_venta' => $first, 'estado' => 'anulado']);
        $this->assertDatabaseHas('entrega', ['id_entrega' => $id, 'id_usuario_modificacion' => $this->driver->id]);
        $this->putJson('/g2/api/entregas/'.$id.'/pedidos', ['pedidos_ids' => [$second]])->assertOk();
        $this->assertDatabaseCount('remito', 2);
        $this->assertDatabaseHas('g2_operacion_eventos', ['dominio' => 'entregas', 'registro' => (string) $id, 'evento' => 'pedidos_modificados']);
        Http::assertNothingSent();
    }

    public function test_other_zone_occupied_or_changed_state_leaves_original_delivery_intact(): void
    {
        $first = $this->sale();
        $id = $this->create([$first])->assertCreated()->json('data.id_entrega');
        $zone = Zona::create(['nombre' => 'Otra zona']);
        $other = Cliente::factory()->create(['creado_por' => $this->admin->id, 'zona_id' => $zone->id]);
        $second = $this->sale($other);
        $this->putJson('/g2/api/entregas/'.$id.'/pedidos', ['pedidos_ids' => [$second]])->assertUnprocessable();
        $this->assertDatabaseHas('entrega_pedido', ['id_entrega' => $id, 'id_venta' => $first]);
        $this->assertDatabaseHas('venta', ['id_venta' => $first, 'estado' => 'preparada']);
        $this->create([$first])->assertStatus(400); // API histórica de alta traduce conflicto de dominio a 400.
        DB::table('entrega')->where('id_entrega', $id)->update(['estado' => 'en_camino']);
        $this->putJson('/g2/api/entregas/'.$id.'/pedidos', ['pedidos_ids' => []])->assertConflict();
    }

    public function test_delivery_confirmation_and_rendition_are_atomic_and_not_editable_afterwards(): void
    {
        $sale = $this->sale();
        $id = $this->create([$sale])->assertCreated()->json('data.id_entrega');
        $this->signInAs($this->driver);
        $this->patchJson('/g2/api/entregas/'.$id.'/pedidos/'.$sale.'/entregado')->assertOk()->assertJsonPath('data.rendicion_precargada', true);
        $this->assertDatabaseHas('venta', ['id_venta' => $sale, 'estado' => 'entregada']);
        $this->assertDatabaseHas('RENDICION', ['id_entrega' => $id, 'total_rendido' => 100]);
        $this->putJson('/g2/api/entregas/'.$id.'/pedidos', ['pedidos_ids' => []])->assertConflict();
        $this->patchJson('/g2/api/entregas/'.$id.'/pedidos/'.$sale.'/entregado')->assertOk();
        $this->assertDatabaseCount('RENDICION_COBRO', 1);
    }

    public function test_other_driver_cannot_modify_delivery_and_zone_is_exposed_by_sales(): void
    {
        $sale = $this->sale();
        $this->getJson('/api/ventas/'.$sale)->assertOk()->assertJsonPath('data.id_zona', $this->client->zona_id);
        $id = $this->create([$sale])->assertCreated()->json('data.id_entrega');
        $other = User::factory()->create(['rol' => 'repartidor']);
        $this->signInAs($other);
        $this->putJson('/g2/api/entregas/'.$id.'/pedidos', ['pedidos_ids' => []])->assertNotFound();
    }

    public function test_later_deliveries_do_not_overwrite_an_already_reviewed_rendition(): void
    {
        $first = $this->sale();
        $second = $this->sale();
        $id = $this->create([$first, $second])->assertCreated()->json('data.id_entrega');
        $this->patchJson('/g2/api/entregas/'.$id.'/pedidos/'.$first.'/entregado')->assertOk();
        app(CajaService::class)->abrirCaja($this->admin->id, 'admin', 0);
        $rendition = DB::table('RENDICION')->where('id_entrega', $id)->value('id_rendicion');
        $this->patchJson('/g2/api/rendiciones/'.$rendition.'/revisar', ['accion' => 'aprobar'])->assertOk();
        $this->patchJson('/g2/api/entregas/'.$id.'/pedidos/'.$second.'/entregado')->assertOk();
        $this->assertDatabaseHas('RENDICION', ['id_rendicion' => $rendition, 'estado' => 'aprobada']);
        $this->assertDatabaseCount('RENDICION',2);
        $this->assertDatabaseCount('RENDICION_COBRO',2);
        $this->assertDatabaseCount('cobro',1);
    }
}
