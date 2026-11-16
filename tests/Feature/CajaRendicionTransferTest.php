<?php

namespace Tests\Feature;

use App\Modules\Caja\Models\Caja;
use App\Modules\Caja\Models\CajaMovimiento;
use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Entregas\Services\EntregaService;
use App\Modules\Rendiciones\Services\RendicionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CajaRendicionTransferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['CAJA_MOVIMIENTO', 'CAJA', 'RENDICION_COBRO', 'RENDICION', 'remito', 'entrega_pedido', 'entrega', 'clientes', 'usuarios'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('usuarios', function (Blueprint $t) {
            $t->id();
            $t->string('nombre')->nullable();
            $t->string('usuario')->nullable();
            $t->string('email')->nullable();
            $t->string('rol')->nullable();
            $t->string('estado')->nullable();
        });

        Schema::create('clientes', function (Blueprint $t) {
            $t->increments('id_cliente');
            $t->string('nombre')->nullable();
            $t->string('apellido_razon_social')->nullable();
            $t->string('dni_cuit')->nullable();
        });

        Schema::create('entrega', function (Blueprint $t) {
            $t->increments('id_entrega');
            $t->unsignedInteger('id_repartidor');
            $t->unsignedInteger('id_usuario_creacion')->default(1);
            $t->dateTime('fecha_creacion')->nullable();
            $t->date('fecha_salida')->nullable();
            $t->string('estado')->default('en_camino');
            $t->string('observaciones')->nullable();
        });

        Schema::create('entrega_pedido', function (Blueprint $t) {
            $t->increments('id_entrega_pedido');
            $t->unsignedInteger('id_entrega');
            $t->unsignedInteger('id_venta');
            $t->string('estado_pedido_entrega')->default('en_reparto');
            $t->decimal('monto_cobrado', 12, 2)->nullable();
            $t->string('medio_pago', 30)->nullable();
            $t->string('motivo_rechazo')->nullable();
            $t->dateTime('fecha_actualizacion')->nullable();
        });

        Schema::create('remito', function (Blueprint $t) {
            $t->increments('id_remito');
            $t->unsignedInteger('id_entrega');
            $t->unsignedInteger('id_venta');
            $t->string('numero_remito')->nullable();
            $t->string('estado')->default('en_reparto');
        });

        Schema::create('RENDICION', function (Blueprint $t) {
            $t->increments('id_rendicion');
            $t->unsignedInteger('id_repartidor');
            $t->unsignedInteger('id_entrega')->nullable();
            $t->dateTime('fecha')->nullable();
            $t->decimal('total_rendido', 12, 2)->default(0);
            $t->string('estado')->default('pendiente');
            $t->string('observaciones')->nullable();
            $t->unsignedInteger('id_usuario_validador')->nullable();
            $t->dateTime('fecha_validacion')->nullable();
            $t->string('motivo_rechazo')->nullable();
        });

        Schema::create('RENDICION_COBRO', function (Blueprint $t) {
            $t->increments('id_rendicion_cobro');
            $t->unsignedInteger('id_rendicion');
            $t->unsignedInteger('id_cliente');
            $t->unsignedInteger('id_factura');
            $t->decimal('monto', 12, 2);
            $t->string('medio_pago')->default('efectivo');
            $t->dateTime('fecha_registro')->nullable();
        });

        Schema::create('CAJA', function (Blueprint $t) {
            $t->increments('id_caja');
            $t->unsignedInteger('id_usuario');
            $t->date('fecha');
            $t->decimal('monto_inicial', 12, 2);
            $t->decimal('monto_final', 12, 2)->nullable();
            $t->decimal('monto_esperado', 12, 2)->nullable();
            $t->decimal('diferencia', 12, 2)->nullable();
            $t->boolean('cierre_automatico')->default(false);
            $t->string('estado', 20)->default('abierta');
            $t->unsignedInteger('id_usuario_cierre')->nullable();
            $t->dateTime('fecha_hora_apertura');
            $t->dateTime('fecha_hora_vencimiento')->nullable();
            $t->dateTime('fecha_hora_cierre')->nullable();
            $t->string('observaciones', 500)->nullable();
        });

        Schema::create('CAJA_MOVIMIENTO', function (Blueprint $t) {
            $t->increments('id_movimiento_caja');
            $t->unsignedInteger('id_caja')->nullable();
            $t->date('fecha');
            $t->dateTime('fecha_hora_carga')->nullable();
            $t->decimal('monto', 12, 2);
            $t->string('tipo', 20);
            $t->string('medio_pago', 20)->default('efectivo');
            $t->string('concepto', 255);
            $t->unsignedInteger('id_usuario');
            $t->decimal('monto_anterior', 12, 2)->nullable();
            $t->unsignedInteger('id_usuario_modificacion')->nullable();
            $t->dateTime('fecha_hora_modificacion')->nullable();
            $t->unsignedInteger('id_cobro')->nullable();
        });

        DB::table('usuarios')->insert([
            ['id' => 1, 'nombre' => 'Admin', 'usuario' => 'admin', 'rol' => 'administrativo', 'estado' => 'activo'],
            ['id' => 7, 'nombre' => 'Repartidor', 'usuario' => 'repartidor', 'rol' => 'repartidor', 'estado' => 'activo'],
        ]);

        DB::table('clientes')->insert([
            ['id_cliente' => 10, 'nombre' => 'Cliente Test', 'apellido_razon_social' => 'Cliente Test SRL', 'dni_cuit' => '30-11111111-1'],
        ]);

        DB::table('entrega')->insert([
            'id_entrega' => 3,
            'id_repartidor' => 7,
            'id_usuario_creacion' => 1,
            'fecha_creacion' => now(),
            'fecha_salida' => now()->toDateString(),
            'estado' => 'en_camino',
        ]);

        DB::table('entrega_pedido')->insert([
            'id_entrega' => 3,
            'id_venta' => 4,
            'estado_pedido_entrega' => 'en_reparto',
        ]);

        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'), 0);
        }

        $ventasMock = \Mockery::mock(VentasApiClient::class);
        $ventasMock->shouldReceive('obtenerVentaPorId')->andReturn([
            'id_venta' => 4,
            'id_cliente' => 10,
            'total' => 1500.0,
            'estado' => 'confirmada',
        ]);
        $ventasMock->shouldReceive('marcarComoEntregada')->andReturn(true);
        $ventasMock->shouldReceive('registrarCobroCuentaCorriente')->andReturn(true);
        $this->app->instance(VentasApiClient::class, $ventasMock);
    }

    public function test_cobro_efectivo_exige_caja_abierta_y_luego_transfiere_de_repartidor_a_admin(): void
    {
        $entregaService = app(EntregaService::class);
        $rendicionService = app(RendicionService::class);

        // 1. Sin caja abierta del repartidor, cobrar en efectivo debe fallar
        try {
            $entregaService->confirmarPedidoEntregado(3, 4, null, 7, 'efectivo', 1500.0);
            $this->fail('Se esperaba excepción por caja cerrada/inexistente del repartidor.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('caja abierta', strtolower($e->getMessage()));
        }

        // 2. Abrir caja del repartidor (id 100) y caja del admin (id 200)
        DB::table('CAJA')->insert([
            [
                'id_caja' => 100,
                'id_usuario' => 7,
                'fecha' => now()->toDateString(),
                'monto_inicial' => 0,
                'estado' => Caja::ESTADO_ABIERTA,
                'fecha_hora_apertura' => now(),
            ],
            [
                'id_caja' => 200,
                'id_usuario' => 1,
                'fecha' => now()->toDateString(),
                'monto_inicial' => 0,
                'estado' => Caja::ESTADO_ABIERTA,
                'fecha_hora_apertura' => now(),
            ],
        ]);

        // 3. Repartidor confirma entrega con cobro en efectivo -> entra a su caja (100)
        $entregaService->confirmarPedidoEntregado(3, 4, null, 7, 'efectivo', 1500.0);

        $movRepartidor = DB::table('CAJA_MOVIMIENTO')->where('id_caja', 100)->get();
        $this->assertCount(1, $movRepartidor);
        $this->assertSame(CajaMovimiento::TIPO_COBRO, $movRepartidor[0]->tipo);
        $this->assertEquals(1500.0, (float) $movRepartidor[0]->monto);

        // 4. Simular que el repartidor cerró su caja antes de la aprobación del admin
        DB::table('CAJA')->where('id_caja', 100)->update([
            'estado' => Caja::ESTADO_CERRADA,
            'fecha_hora_cierre' => now(),
        ]);

        $rendicion = $rendicionService->precargarOActualizarRendicionDesdeEntrega(3);
        $this->assertNotNull($rendicion);

        // 5. Admin aprueba rendición -> egreso en caja del repartidor (100) + ingreso en caja del admin (200)
        $rendicionService->revisarRendicion((int) $rendicion['id_rendicion'], [
            'accion' => 'aprobar',
            'id_usuario_validador' => 1,
        ]);

        $egresosRep = DB::table('CAJA_MOVIMIENTO')
            ->where('id_caja', 100)
            ->where('tipo', CajaMovimiento::TIPO_EGRESO)
            ->get();
        $this->assertCount(1, $egresosRep);
        $this->assertEquals(1500.0, (float) $egresosRep[0]->monto);

        $ingresosAdmin = DB::table('CAJA_MOVIMIENTO')
            ->where('id_caja', 200)
            ->where('tipo', CajaMovimiento::TIPO_COBRO)
            ->get();
        $this->assertCount(1, $ingresosAdmin);
        $this->assertEquals(1500.0, (float) $ingresosAdmin[0]->monto);
    }
}
