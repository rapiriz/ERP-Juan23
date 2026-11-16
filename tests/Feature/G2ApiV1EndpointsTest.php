<?php

namespace Tests\Feature;

use App\Modules\Clientes\Models\Cliente;
use App\Modules\Login\Enums\UserRole;
use App\Modules\Login\Models\User;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Models\RendicionCobro;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class G2ApiV1EndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function conTokenDeServicio(): self
    {
        config(['services.internal_api.token' => 'g2-internal-token']);

        return $this->withToken('g2-internal-token');
    }

    public function test_todos_los_endpoints_g2_v1_exigen_autenticacion(): void
    {
        $rutas = [
            '/api/v1/caja/cierres',
            '/api/v1/caja/cierres/1',
            '/api/v1/caja/2026-11-13/1',
            '/api/v1/caja/2026-11-13',
            '/api/v1/rendiciones',
            '/api/v1/rendiciones/1',
            '/api/v1/cobros',
            '/api/v1/entregas',
            '/api/v1/entregas/1',
            '/api/v1/remitos/1',
            '/api/v1/conciliacion/movimientos',
            '/api/v1/conciliacion/cheques',
        ];

        foreach ($rutas as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
    }

    public function test_caja_cierres_y_por_usuario_y_fecha_devuelven_formato_data(): void
    {
        Schema::dropIfExists('CAJA_MOVIMIENTO');
        Schema::dropIfExists('CAJA');

        Schema::create('CAJA', function (Blueprint $table) {
            $table->increments('id_caja');
            $table->unsignedInteger('id_usuario');
            $table->date('fecha');
            $table->dateTime('fecha_hora_apertura');
            $table->dateTime('fecha_hora_cierre')->nullable();
            $table->dateTime('fecha_hora_vencimiento')->nullable();
            $table->decimal('monto_inicial', 12, 2)->default(0);
            $table->decimal('monto_final', 12, 2)->nullable();
            $table->decimal('monto_esperado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->string('estado', 20)->default('abierta');
            $table->boolean('cierre_automatico')->default(false);
            $table->unsignedInteger('id_usuario_cierre')->nullable();
            $table->text('observaciones')->nullable();
        });

        Schema::create('CAJA_MOVIMIENTO', function (Blueprint $table) {
            $table->increments('id_movimiento_caja');
            $table->unsignedInteger('id_caja')->nullable();
            $table->unsignedInteger('id_cobro')->nullable();
            $table->unsignedInteger('id_usuario');
            $table->date('fecha');
            $table->dateTime('fecha_hora_carga');
            $table->string('tipo', 30);
            $table->string('medio_pago', 30)->default('efectivo');
            $table->string('concepto', 255);
            $table->decimal('monto', 12, 2);
            $table->decimal('monto_anterior', 12, 2)->nullable();
            $table->unsignedInteger('id_usuario_modificacion')->nullable();
            $table->dateTime('fecha_hora_modificacion')->nullable();
        });

        DB::table('CAJA')->insert([
            'id_caja' => 1,
            'id_usuario' => 5,
            'fecha' => '2026-11-13',
            'fecha_hora_apertura' => '2026-11-13 08:00:00',
            'fecha_hora_cierre' => '2026-11-13 18:00:00',
            'monto_inicial' => 1000,
            'monto_final' => 1500,
            'monto_esperado' => 1500,
            'diferencia' => 0,
            'estado' => 'cerrada',
            'cierre_automatico' => false,
        ]);

        $this->conTokenDeServicio()
            ->getJson('/api/v1/caja/cierres')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_caja', 1);

        $this->conTokenDeServicio()
            ->getJson('/api/v1/caja/cierres/1')
            ->assertOk()
            ->assertJsonPath('data.id_caja', 1)
            ->assertJsonPath('data.tipo_cierre', 'manual');

        $this->conTokenDeServicio()
            ->getJson('/api/v1/caja/cierres/999')
            ->assertNotFound();

        $this->conTokenDeServicio()
            ->getJson('/api/v1/caja/2026-11-13/5')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rendiciones_show_y_cobros_estandarizados(): void
    {
        Schema::dropIfExists('RENDICION_COBRO');
        Schema::dropIfExists('RENDICION');

        Schema::create('RENDICION', function (Blueprint $table) {
            $table->increments('id_rendicion');
            $table->unsignedInteger('id_repartidor');
            $table->unsignedInteger('id_entrega')->nullable();
            $table->date('fecha');
            $table->decimal('total_rendido', 12, 2)->default(0);
            $table->string('estado', 20)->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('id_usuario_validador')->nullable();
            $table->dateTime('fecha_validacion')->nullable();
            $table->text('motivo_rechazo')->nullable();
        });

        Schema::create('RENDICION_COBRO', function (Blueprint $table) {
            $table->increments('id_rendicion_cobro');
            $table->unsignedInteger('id_rendicion');
            $table->unsignedInteger('id_cliente');
            $table->unsignedInteger('id_factura');
            $table->decimal('monto', 12, 2);
            $table->string('medio_pago', 30);
            $table->dateTime('fecha_registro');
        });

        $clienteId = Cliente::factory()->create()->id;

        $rendicion = Rendicion::create([
            'id_repartidor' => 2,
            'id_entrega' => 10,
            'fecha' => '2026-11-13',
            'total_rendido' => 2500,
            'estado' => 'aprobada',
            'observaciones' => 'Rendicion OK',
        ]);

        RendicionCobro::create([
            'id_rendicion' => $rendicion->id_rendicion,
            'id_cliente' => $clienteId,
            'id_factura' => 55,
            'monto' => 2500,
            'medio_pago' => 'efectivo',
            'fecha_registro' => '2026-11-13 12:00:00',
        ]);

        $this->conTokenDeServicio()
            ->getJson("/api/v1/rendiciones/{$rendicion->id_rendicion}")
            ->assertOk()
            ->assertJsonPath('data.id_rendicion', $rendicion->id_rendicion)
            ->assertJsonPath('data.cobros.0.venta_id', 55);

        $this->conTokenDeServicio()
            ->getJson('/api/v1/rendiciones/99999')
            ->assertNotFound();

        // Cobros sin cliente_id ahora devuelve listado paginado estándar.
        $this->conTokenDeServicio()
            ->getJson('/api/v1/cobros')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.cliente_id', $clienteId)
            ->assertJsonPath('data.0.venta_id', 55)
            ->assertJsonPath('data.0.observaciones', 'Rendicion OK');

        $this->conTokenDeServicio()
            ->getJson('/api/v1/cobros?cliente_id=999999')
            ->assertNotFound();
    }

    public function test_entregas_y_remitos_stateless(): void
    {
        Schema::dropIfExists('detalle_remito');
        Schema::dropIfExists('remito');
        Schema::dropIfExists('entrega_pedido');
        Schema::dropIfExists('entrega');

        $this->conTokenDeServicio()
            ->getJson('/api/v1/entregas')
            ->assertStatus(503);

        Schema::create('entrega', function (Blueprint $table) {
            $table->increments('id_entrega');
            $table->unsignedInteger('id_repartidor');
            $table->dateTime('fecha_creacion')->nullable();
            $table->date('fecha_salida');
            $table->string('estado', 30);
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('id_usuario_creacion')->nullable();
        });
        Schema::create('entrega_pedido', function (Blueprint $table) {
            $table->increments('id_entrega_pedido');
            $table->unsignedInteger('id_entrega');
            $table->unsignedInteger('id_venta');
            $table->string('estado_pedido_entrega', 30);
            $table->text('motivo_rechazo')->nullable();
        });
        Schema::create('remito', function (Blueprint $table) {
            $table->increments('id_remito');
            $table->string('numero_remito', 40);
            $table->unsignedInteger('id_entrega');
            $table->unsignedInteger('id_venta');
            $table->dateTime('fecha_emision')->nullable();
            $table->string('estado', 30);
            $table->text('observaciones')->nullable();
        });
        Schema::create('detalle_remito', function (Blueprint $table) {
            $table->increments('id_detalle_remito');
            $table->unsignedInteger('id_remito');
            $table->unsignedInteger('id_producto')->nullable();
            $table->string('descripcion_producto', 255)->nullable();
            $table->integer('cantidad')->default(0);
            $table->integer('bultos')->default(0);
        });

        $repartidor = User::factory()->create(['rol' => UserRole::REPARTIDOR->value]);
        DB::table('entrega')->insert([
            'id_entrega' => 7,
            'id_repartidor' => $repartidor->id,
            'fecha_creacion' => '2026-11-13 07:00:00',
            'fecha_salida' => '2026-11-13',
            'estado' => 'en_camino',
        ]);
        DB::table('entrega_pedido')->insert([
            'id_entrega' => 7,
            'id_venta' => 88,
            'estado_pedido_entrega' => 'en_camino',
        ]);
        DB::table('remito')->insert([
            'id_remito' => 3,
            'numero_remito' => '0001-00000003',
            'id_entrega' => 7,
            'id_venta' => 88,
            'fecha_emision' => '2026-11-13 07:05:00',
            'estado' => 'emitido',
        ]);
        DB::table('detalle_remito')->insert([
            'id_remito' => 3,
            'id_producto' => 12,
            'descripcion_producto' => 'Caja x6',
            'cantidad' => 12,
            'bultos' => 2,
        ]);

        $this->conTokenDeServicio()
            ->getJson('/api/v1/entregas')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.id_entrega', 7)
            ->assertJsonPath('data.0.total_bultos', 2);

        $this->conTokenDeServicio()
            ->getJson('/api/v1/entregas/7')
            ->assertOk()
            ->assertJsonPath('data.pedidos.0.id_venta', 88)
            ->assertJsonPath('data.pedidos.0.numero_remito', '0001-00000003');

        $this->conTokenDeServicio()
            ->getJson('/api/v1/remitos/3')
            ->assertOk()
            ->assertJsonPath('data.numero_remito', '0001-00000003')
            ->assertJsonPath('data.items.0.bultos', 2);

        $this->conTokenDeServicio()
            ->getJson('/api/v1/remitos/999')
            ->assertNotFound();
    }

    public function test_conciliacion_movimientos_y_cheques_stateless(): void
    {
        Schema::dropIfExists('CHEQUE');
        Schema::dropIfExists('MOVIMIENTO_BANCARIO');

        $this->conTokenDeServicio()
            ->getJson('/api/v1/conciliacion/movimientos')
            ->assertStatus(503);

        Schema::create('MOVIMIENTO_BANCARIO', function (Blueprint $table) {
            $table->increments('id_movimiento_bancario');
            $table->unsignedInteger('id_archivo_importado_log')->nullable();
            $table->date('fecha_operacion');
            $table->decimal('monto', 12, 2);
            $table->string('signo_monto', 5)->default('+');
            $table->string('descripcion_original', 255)->nullable();
            $table->string('descripcion_normalizada', 255)->nullable();
            $table->string('nro_comprobante_extraido', 80)->nullable();
            $table->string('cuit_extraido', 30)->nullable();
            $table->string('tipo_movimiento', 40)->nullable();
            $table->string('estado_conciliacion', 30)->default('pendiente');
            $table->timestamps();
        });

        Schema::create('CHEQUE', function (Blueprint $table) {
            $table->increments('id_cheque');
            $table->unsignedInteger('id_cobro')->nullable();
            $table->string('numero_cheque', 60);
            $table->string('banco_emisor', 100)->nullable();
            $table->date('fecha_cobro')->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->date('fecha_deposito')->nullable();
            $table->date('fecha_rechazo')->nullable();
        });

        DB::table('MOVIMIENTO_BANCARIO')->insert([
            'fecha_operacion' => '2026-11-13',
            'monto' => 4500.50,
            'signo_monto' => '+',
            'descripcion_original' => 'TRANSF CLIENTE',
            'tipo_movimiento' => 'transferencia',
            'estado_conciliacion' => 'pendiente',
        ]);

        DB::table('CHEQUE')->insert([
            'id_cobro' => 44,
            'numero_cheque' => 'CHQ-991',
            'banco_emisor' => 'Nacion',
            'fecha_cobro' => '2026-11-15',
            'estado' => 'depositado',
        ]);

        $this->conTokenDeServicio()
            ->getJson('/api/v1/conciliacion/movimientos?estado=pendiente')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.monto', '4500.50');

        $this->conTokenDeServicio()
            ->getJson('/api/v1/conciliacion/cheques?estado=depositado')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('data.0.numero_cheque', 'CHQ-991');
    }
}
