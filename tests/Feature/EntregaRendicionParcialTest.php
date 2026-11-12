<?php

namespace Tests\Feature;

use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Entregas\Repositories\EntregaRepository;
use App\Modules\Entregas\Services\EntregaService;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Repositories\RendicionRepository;
use App\Modules\Rendiciones\Services\RendicionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntregaRendicionParcialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('clientes');
        Schema::dropIfExists('RENDICION_COBRO');
        Schema::dropIfExists('RENDICION');
        Schema::dropIfExists('remito');
        Schema::dropIfExists('entrega_pedido');
        Schema::dropIfExists('entrega');
        Schema::dropIfExists('usuarios');

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('usuario')->nullable();
            $table->string('email')->nullable();
            $table->string('rol')->nullable();
            $table->string('estado')->nullable();
        });

        Schema::create('entrega', function (Blueprint $table) {
            $table->increments('id_entrega');
            $table->unsignedInteger('id_repartidor');
            $table->unsignedInteger('id_usuario_creacion')->default(1);
            $table->dateTime('fecha_creacion')->nullable();
            $table->date('fecha_salida')->nullable();
            $table->string('estado')->default('en_camino');
            $table->string('observaciones')->nullable();
        });

        Schema::create('entrega_pedido', function (Blueprint $table) {
            $table->increments('id_entrega_pedido');
            $table->unsignedInteger('id_entrega');
            $table->unsignedInteger('id_venta');
            $table->string('estado_pedido_entrega')->default('en_reparto');
            $table->decimal('monto_cobrado', 12, 2)->nullable();
            $table->string('medio_pago', 30)->nullable();
            $table->string('motivo_rechazo')->nullable();
            $table->dateTime('fecha_actualizacion')->nullable();
        });

        Schema::create('remito', function (Blueprint $table) {
            $table->increments('id_remito');
            $table->unsignedInteger('id_entrega');
            $table->unsignedInteger('id_venta');
            $table->string('numero_remito')->nullable();
            $table->string('estado')->default('en_reparto');
        });

        Schema::create('RENDICION', function (Blueprint $table) {
            $table->increments('id_rendicion');
            $table->unsignedInteger('id_repartidor');
            $table->unsignedInteger('id_entrega')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->decimal('total_rendido', 12, 2)->default(0);
            $table->string('estado')->default('pendiente');
            $table->string('observaciones')->nullable();
            $table->unsignedInteger('id_usuario_validador')->nullable();
            $table->dateTime('fecha_validacion')->nullable();
            $table->string('motivo_rechazo')->nullable();
        });

        Schema::create('RENDICION_COBRO', function (Blueprint $table) {
            $table->increments('id_rendicion_cobro');
            $table->unsignedInteger('id_rendicion');
            $table->unsignedInteger('id_cliente');
            $table->unsignedInteger('id_factura');
            $table->decimal('monto', 12, 2);
            $table->string('medio_pago')->default('efectivo');
            $table->dateTime('fecha_registro')->nullable();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre')->nullable();
            $table->string('apellido_razon_social')->nullable();
            $table->string('dni_cuit')->nullable();
            $table->string('direccion')->nullable();
            $table->string('localidad')->nullable();
            $table->unsignedInteger('zona_id')->nullable();
            $table->string('estado')->nullable();
        });

        DB::table('clientes')->insert([
            'id' => 10,
            'nombre' => '',
            'apellido_razon_social' => 'Distribuidora Sur S.R.L.',
            'dni_cuit' => '30-71234567-8',
            'estado' => 'activo',
        ]);

        DB::table('usuarios')->insert([
            'id' => 7,
            'nombre' => 'Chofer Test',
            'usuario' => 'chofer',
            'rol' => 'repartidor',
            'estado' => 'activo',
        ]);

        DB::table('entrega')->insert([
            'id_entrega' => 3,
            'id_repartidor' => 7,
            'id_usuario_creacion' => 7,
            'fecha_salida' => '2026-11-12',
            'estado' => 'en_camino',
        ]);

        DB::table('entrega_pedido')->insert([
            ['id_entrega' => 3, 'id_venta' => 4, 'estado_pedido_entrega' => 'en_reparto'],
            ['id_entrega' => 3, 'id_venta' => 3, 'estado_pedido_entrega' => 'en_reparto'],
        ]);

        DB::table('remito')->insert([
            ['id_entrega' => 3, 'id_venta' => 4, 'numero_remito' => 'REM-0004', 'estado' => 'en_reparto'],
            ['id_entrega' => 3, 'id_venta' => 3, 'numero_remito' => 'REM-0003', 'estado' => 'en_reparto'],
        ]);
    }

    private function mockVentasClient(): VentasApiClient
    {
        $mock = $this->createMock(VentasApiClient::class);
        $mock->method('obtenerVentaPorId')->willReturnCallback(function (int $id) {
            return match ($id) {
                4 => ['id' => 4, 'total' => 3900.00, 'id_cliente' => 10, 'cliente_nombre' => 'Cliente 4'],
                3 => ['id' => 3, 'total' => 2500.00, 'id_cliente' => 11, 'cliente_nombre' => 'Cliente 3'],
                default => ['id' => $id, 'total' => 0.0, 'id_cliente' => 1, 'cliente_nombre' => 'Desconocido'],
            };
        });

        return $mock;
    }

    public function test_crea_nueva_rendicion_pendiente_para_segundo_pedido_cuando_la_primera_ya_fue_aprobada_y_no_cierra_viaje_parcial(): void
    {
        $ventasMock = $this->mockVentasClient();
        $service = new RendicionService(new RendicionRepository(), $ventasMock);

        // 1. Se entrega el primer pedido (#PED-0004)
        DB::table('entrega_pedido')
            ->where('id_entrega', 3)
            ->where('id_venta', 4)
            ->update(['estado_pedido_entrega' => 'entregado']);

        $r1 = $service->precargarOActualizarRendicionDesdeEntrega(3);
        $this->assertNotNull($r1);
        $this->assertEquals(3900.00, $r1['total_rendido']);
        $this->assertEquals(Rendicion::ESTADO_PENDIENTE, $r1['estado']);

        // 2. Se aprueba la primera rendición mientras el #PED-0003 sigue en reparto
        $aprobada = $service->revisarRendicion($r1['id_rendicion'], [
            'accion' => 'aprobar',
            'id_usuario_validador' => 1,
        ]);
        $this->assertEquals(Rendicion::ESTADO_APROBADA, $aprobada['estado_actual']);

        // El viaje NO debe cerrarse como 'finalizada' porque falta entregar el pedido #3
        $entrega = DB::table('entrega')->where('id_entrega', 3)->first();
        $this->assertEquals('en_camino', $entrega->estado);

        // obtenerDatosParaRendir no debe volver a incluir el pedido #4 ya aprobado
        $datosRestantes = $service->obtenerDatosParaRendir(3);
        $this->assertCount(1, $datosRestantes['cobros_sugeridos']);
        $this->assertEquals(3, $datosRestantes['cobros_sugeridos'][0]['id_venta']);

        // 3. Se entrega el segundo pedido (#PED-0003)
        DB::table('entrega_pedido')
            ->where('id_entrega', 3)
            ->where('id_venta', 3)
            ->update(['estado_pedido_entrega' => 'entregado']);

        $r2 = $service->precargarOActualizarRendicionDesdeEntrega(3);
        $this->assertNotNull($r2);
        $this->assertNotEquals($r1['id_rendicion'], $r2['id_rendicion']);
        $this->assertEquals(2500.00, $r2['total_rendido']);
        $this->assertEquals(Rendicion::ESTADO_PENDIENTE, $r2['estado']);

        $cobrosR2 = DB::table('RENDICION_COBRO')->where('id_rendicion', $r2['id_rendicion'])->get();
        $this->assertCount(1, $cobrosR2);
        $this->assertEquals(3, (int) $cobrosR2[0]->id_factura);
    }

    public function test_precargar_en_rendicion_crea_rendiciones_individuales_por_pedido(): void
    {
        $ventasMock = $this->mockVentasClient();
        $service = new RendicionService(new RendicionRepository(), $ventasMock);

        DB::table('entrega_pedido')
            ->where('id_entrega', 3)
            ->where('id_venta', 4)
            ->update(['estado_pedido_entrega' => 'entregado']);

        $r1 = $service->precargarOActualizarRendicionDesdeEntrega(3);
        $this->assertEquals(3900.00, $r1['total_rendido']);

        DB::table('entrega_pedido')
            ->where('id_entrega', 3)
            ->where('id_venta', 3)
            ->update(['estado_pedido_entrega' => 'entregado']);

        $r2 = $service->precargarOActualizarRendicionDesdeEntrega(3);
        $this->assertNotEquals($r1['id_rendicion'], $r2['id_rendicion']);
        $this->assertEquals(2500.00, $r2['total_rendido']);

        // Cada rendición tiene su propio cobro individual
        $cobrosR1 = DB::table('RENDICION_COBRO')->where('id_rendicion', $r1['id_rendicion'])->get();
        $this->assertCount(1, $cobrosR1);
        $this->assertEquals(4, (int) $cobrosR1[0]->id_factura);

        $cobrosR2 = DB::table('RENDICION_COBRO')->where('id_rendicion', $r2['id_rendicion'])->get();
        $this->assertCount(1, $cobrosR2);
        $this->assertEquals(3, (int) $cobrosR2[0]->id_factura);
    }

    public function test_confirmar_pedido_entregado_notifica_una_sola_vez_a_ventas_g4(): void
    {
        $ventasMock = $this->createMock(VentasApiClient::class);
        $ventasMock->method('obtenerVentaPorId')->willReturn([
            'id' => 4,
            'total' => 3900.00,
            'id_cliente' => 10,
            'cliente_nombre' => 'Cliente 4',
        ]);
        $ventasMock->expects($this->once())
            ->method('actualizarEstadoVenta')
            ->with(4, 'entregada')
            ->willReturn(true);

        $this->app->instance(VentasApiClient::class, $ventasMock);

        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'), 0);
        }

        $repo = new EntregaRepository($pdo, $ventasMock);
        $entregaService = new EntregaService($repo, null, $ventasMock);

        $res = $entregaService->confirmarPedidoEntregado(3, 4, null, 7);
        $this->assertEquals('entregado', $res['estado_pedido']);
    }

    public function test_obtener_detalle_y_comprobante_imprimible_muestran_razon_social_y_cuit_del_cliente(): void
    {
        $ventasMock = $this->mockVentasClient();
        $service = new RendicionService(new RendicionRepository(), $ventasMock);

        DB::table('entrega_pedido')
            ->where('id_entrega', 3)
            ->where('id_venta', 4)
            ->update(['estado_pedido_entrega' => 'entregado']);

        $r1 = $service->precargarOActualizarRendicionDesdeEntrega(3);
        $detalle = $service->obtenerDetalle($r1['id_rendicion']);

        $this->assertEquals('Chofer Test', $detalle['repartidor_nombre']);
        $this->assertCount(1, $detalle['cobros']);
        $this->assertEquals('Distribuidora Sur S.R.L.', $detalle['cobros'][0]['cliente_nombre']);
        $this->assertEquals('30-71234567-8', $detalle['cobros'][0]['cliente_cuit']);

        $html = view('rendiciones.imprimir', ['rendicion' => $detalle])->render();
        $this->assertStringContainsString('Distribuidora Sur S.R.L.', $html);
        $this->assertStringContainsString('30-71234567-8', $html);
        $this->assertStringNotContainsString('Cliente #10', $html);
    }

    public function test_pago_parcial_y_medio_de_pago_distinto_de_efectivo_viajan_a_rendicion(): void
    {
        $ventasMock = $this->mockVentasClient();
        $ventasMock->expects($this->once())
            ->method('actualizarEstadoVenta')
            ->with(4, 'entregada')
            ->willReturn(true);

        $this->app->instance(VentasApiClient::class, $ventasMock);

        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'), 0);
        }

        $repo = new EntregaRepository($pdo, $ventasMock);
        $entregaService = new EntregaService($repo, null, $ventasMock);

        // El chofer confirma entrega del pedido #4 ($3.900) cobrando un pago parcial de $2.000 por transferencia
        $entregaService->confirmarPedidoEntregado(3, 4, 'Pago parcial', 7, 'transferencia', 2000.00);

        $ep = DB::table('entrega_pedido')->where('id_entrega', 3)->where('id_venta', 4)->first();
        $this->assertEquals(2000.00, (float) $ep->monto_cobrado);
        $this->assertEquals('transferencia', $ep->medio_pago);

        $rendicion = DB::table('RENDICION')->where('id_entrega', 3)->first();
        $this->assertNotNull($rendicion);
        $this->assertEquals(2000.00, (float) $rendicion->total_rendido);

        $cobro = DB::table('RENDICION_COBRO')->where('id_rendicion', $rendicion->id_rendicion)->first();
        $this->assertNotNull($cobro);
        $this->assertEquals(2000.00, (float) $cobro->monto);
        $this->assertEquals('transferencia', $cobro->medio_pago);

        $rendicionService = new RendicionService(new RendicionRepository(), $ventasMock);
        $datosPreparar = $rendicionService->obtenerDatosParaRendir(3);
        $this->assertEquals(2000.00, $datosPreparar['cobros_sugeridos'][0]['monto']);
        $this->assertEquals(3900.00, $datosPreparar['cobros_sugeridos'][0]['monto_total_pedido']);
        $this->assertEquals('transferencia', $datosPreparar['cobros_sugeridos'][0]['medio_pago']);
    }

    public function test_entrega_sin_cobro_en_el_momento_no_inserta_cobro_en_cero(): void
    {
        $ventasMock = $this->mockVentasClient();
        $this->app->instance(VentasApiClient::class, $ventasMock);

        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'), 0);
        }

        $repo = new EntregaRepository($pdo, $ventasMock);
        $entregaService = new EntregaService($repo, null, $ventasMock);

        $entregaService->confirmarPedidoEntregado(3, 3, 'Cuenta corriente', 7, 'efectivo', 0.00);

        $this->assertEquals(0, DB::table('RENDICION')->where('id_entrega', 3)->count());
        $this->assertEquals(0, DB::table('RENDICION_COBRO')->count());
    }

    public function test_obtener_datos_para_rendir_muestra_saldo_pendiente_luego_de_aprobar_pago_parcial(): void
    {
        $ventasMock = $this->mockVentasClient();
        $this->app->instance(VentasApiClient::class, $ventasMock);

        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'), 0);
        }

        $repo = new EntregaRepository($pdo, $ventasMock);
        $entregaService = new EntregaService($repo, null, $ventasMock);
        $rendicionService = new RendicionService(new RendicionRepository(), $ventasMock);

        // 1. Chofer cobra $2.000 en efectivo de un pedido de $3.900
        $entregaService->confirmarPedidoEntregado(3, 4, 'Pago parcial en efectivo', 7, 'efectivo', 2000.00);
        $rendicionParcial = DB::table('RENDICION')->where('id_entrega', 3)->first();

        // 2. Se aprueba esa rendición parcial ($2.000)
        $rendicionService->revisarRendicion($rendicionParcial->id_rendicion, [
            'accion' => 'aprobar',
            'id_usuario_validador' => 1,
        ]);

        // 3. Al abrir "Cargar Rendición de Reparto" para cargar la transferencia restante ($1.900),
        // el pedido #4 sigue apareciendo con el saldo pendiente de $1.900
        $datos = $rendicionService->obtenerDatosParaRendir(3);
        $pedido4 = collect($datos['cobros_sugeridos'])->firstWhere('id_venta', 4);
        $this->assertNotNull($pedido4);
        $this->assertEquals(1900.00, $pedido4['monto']);
        $this->assertEquals(2000.00, $pedido4['monto_ya_rendido']);
        $this->assertEquals(1900.00, $pedido4['saldo_pendiente']);

        // 4. Se registra y aprueba el saldo restante ($1.900 por transferencia)
        $r2 = $rendicionService->registrarRendicion([
            'id_repartidor' => 7,
            'id_entrega' => 3,
            'cobros' => [[
                'id_cliente' => 10,
                'id_factura' => 4,
                'monto' => 1900.00,
                'medio_pago' => 'transferencia',
            ]],
        ]);
        $rendicionService->revisarRendicion($r2['id_rendicion'], [
            'accion' => 'aprobar',
            'id_usuario_validador' => 1,
        ]);

        // 5. Ahora que está 100% rendido y aprobado, el pedido #4 ya no aparece pendiente
        $datosFinal = $rendicionService->obtenerDatosParaRendir(3);
        $this->assertNull(collect($datosFinal['cobros_sugeridos'])->firstWhere('id_venta', 4));
    }
}
