<?php

namespace Tests\Feature;

use App\Modules\Login\Enums\UserRole;
use App\Modules\Login\Enums\UserStatus;
use App\Modules\Login\Models\User;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Shared\Http\Middleware\G2SchemaReady;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pruebas de integración para los Endpoints HTTP del módulo Rendiciones (Grupo 2).
 */
class RendicionesEndpointsTest extends TestCase
{
    private function autenticarComo(UserRole $rol, int $id = 1): User
    {
        $token = 'test-session-rendiciones';
        $user = new User();
        $user->forceFill([
            'id' => $id,
            'nombre' => 'Usuario Test Rendiciones',
            'usuario' => 'test-rendicion',
            'rol' => $rol,
            'estado' => UserStatus::ACTIVO,
            'current_session_id' => hash('sha256', $token),
        ]);
        $this->actingAs($user)->withSession(['session_token' => $token, 'last_activity' => now()->timestamp]);
        return $user;
    }

    private function prepararEsquema(): void
    {
        $this->withoutMiddleware(G2SchemaReady::class);

        if (!Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nombre');
                $table->string('usuario')->unique();
                $table->string('email')->unique();
                $table->string('rol');
                $table->string('estado')->default('activo');
                $table->string('current_session_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('RENDICION')) {
            Schema::create('RENDICION', function (Blueprint $table) {
                $table->increments('id_rendicion');
                $table->integer('id_repartidor');
                $table->integer('id_entrega')->nullable();
                $table->date('fecha');
                $table->decimal('total_rendido', 12, 2)->default(0);
                $table->string('estado', 20)->default('pendiente');
                $table->text('observaciones')->nullable();
                $table->integer('id_usuario_validador')->nullable();
                $table->dateTime('fecha_validacion')->nullable();
                $table->text('motivo_rechazo')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('RENDICION_COBRO')) {
            Schema::create('RENDICION_COBRO', function (Blueprint $table) {
                $table->increments('id_rendicion_cobro');
                $table->integer('id_rendicion');
                $table->integer('id_cliente');
                $table->integer('id_factura')->nullable();
                $table->decimal('monto', 12, 2);
                $table->string('medio_pago', 20)->default('efectivo');
                $table->dateTime('fecha_registro')->nullable();
            });
        }
    }

    public function test_invitado_sin_sesion_recibe_redireccion_o_401(): void
    {
        $res = $this->get('/rendiciones');
        $res->assertRedirect('/login');

        $apiRes = $this->getJson('/g2/api/rendiciones');
        $apiRes->assertStatus(401);
    }

    public function test_usuario_autenticado_accede_a_pantalla_rendiciones(): void
    {
        $this->prepararEsquema();
        $this->autenticarComo(UserRole::ADMINISTRATIVO);

        $res = $this->get('/rendiciones');
        $res->assertOk();
        $res->assertSee('Rendiciones');
    }

    public function test_listar_rendiciones_devuelve_json_estructurado(): void
    {
        $this->prepararEsquema();
        $this->autenticarComo(UserRole::ADMINISTRATIVO);

        Rendicion::create([
            'id_repartidor' => 10,
            'fecha' => now()->toDateString(),
            'total_rendido' => 25000,
            'estado' => 'pendiente',
        ]);

        $res = $this->getJson('/g2/api/rendiciones');
        $res->assertOk();
        $res->assertJsonPath('error', false);
        $this->assertNotEmpty($res->json('data'));
    }

    public function test_repartidor_solo_lista_sus_propias_rendiciones(): void
    {
        $this->prepararEsquema();
        $this->autenticarComo(UserRole::REPARTIDOR, 42);

        Rendicion::create([
            'id_repartidor' => 42,
            'fecha' => now()->toDateString(),
            'total_rendido' => 10000,
            'estado' => 'pendiente',
        ]);
        Rendicion::create([
            'id_repartidor' => 99,
            'fecha' => now()->toDateString(),
            'total_rendido' => 50000,
            'estado' => 'pendiente',
        ]);

        $res = $this->getJson('/g2/api/rendiciones');
        $res->assertOk();
        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(42, $data[0]['id_repartidor']);
    }

    public function test_api_v1_rendiciones_publica_permite_paginacion(): void
    {
        $this->prepararEsquema();

        Rendicion::create([
            'id_repartidor' => 1,
            'fecha' => now()->toDateString(),
            'total_rendido' => 1500,
            'estado' => 'aprobada',
        ]);

        $res = $this->getJson('/api/v1/rendiciones');
        $res->assertOk();
        $res->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
    }

    public function test_comprobante_imprimible_responde_ok(): void
    {
        $this->prepararEsquema();
        $this->autenticarComo(UserRole::ADMINISTRATIVO);

        $r = Rendicion::create([
            'id_repartidor' => 1,
            'fecha' => now()->toDateString(),
            'total_rendido' => 8500,
            'estado' => 'pendiente',
        ]);

        $res = $this->get("/rendiciones/{$r->id_rendicion}/imprimir");
        $res->assertOk();
        $res->assertSee('DISTRIBUIDORA JUAN XXIII');
        $res->assertSee('Comprobante Interno de Rendición');
    }
}
