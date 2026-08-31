<?php
/**
 * Test Suite de Unidad: Módulo de Entregas (HU #1 y HU #2)
 * Ejecutable directamente con: php tests/EntregasTest.php
 */

require_once __DIR__ . '/../src/Shared/Database/Conexion.php';
require_once __DIR__ . '/../src/Shared/Http/Response.php';
require_once __DIR__ . '/../src/Entregas/Models/Entrega.php';
require_once __DIR__ . '/../src/Entregas/Models/Remito.php';
require_once __DIR__ . '/../src/Entregas/Repositories/EntregaRepository.php';
require_once __DIR__ . '/../src/Entregas/Repositories/RemitoRepository.php';
require_once __DIR__ . '/../src/Entregas/Services/EntregaService.php';

use Shared\Database\Conexion;
use Entregas\Services\EntregaService;
use Entregas\Repositories\EntregaRepository;
use Entregas\Repositories\RemitoRepository;

echo "====================================================\n";
echo "🧪 INICIANDO TEST SUITE: MÓDULO ENTREGAS (HU #1 & #2)\n";
echo "====================================================\n\n";

$testsPasados = 0;
$testsFallados = 0;

function assertCustom(bool $condicion, string $nombreTest) {
    global $testsPasados, $testsFallados;
    if ($condicion) {
        echo "  ✅ PASÓ: {$nombreTest}\n";
        $testsPasados++;
    } else {
        echo "  ❌ FALLÓ: {$nombreTest}\n";
        $testsFallados++;
    }
}

// Configurar base de datos de prueba en memoria SQLite
$pdoTest = new PDO('sqlite::memory:');
$pdoTest->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Crear tablas en memoria
$pdoTest->exec("
    CREATE TABLE zona (id_zona INTEGER PRIMARY KEY, nombre TEXT, descripcion TEXT);
    CREATE TABLE cliente (id_cliente INTEGER PRIMARY KEY, nombre TEXT, razon_social TEXT, cuit TEXT, direccion TEXT, localidad TEXT, telefono TEXT, id_zona INTEGER, estado TEXT);
    CREATE TABLE usuario (id_usuario INTEGER PRIMARY KEY, nombre TEXT, user TEXT, password TEXT, rol TEXT, estado TEXT);
    CREATE TABLE producto (id_producto INTEGER PRIMARY KEY, codigo TEXT, nombre TEXT, descripcion TEXT, precio_unitario REAL, bultos_por_unidad INTEGER, stock INTEGER, estado TEXT);
    CREATE TABLE venta (id_venta INTEGER PRIMARY KEY, id_cliente INTEGER, id_usuario INTEGER, fecha TEXT, total REAL, total_bultos INTEGER, total_sueltos INTEGER, estado TEXT, observaciones TEXT);
    CREATE TABLE detalle_venta (id_detalle_venta INTEGER PRIMARY KEY, id_venta INTEGER, id_producto INTEGER, cantidad INTEGER, precio_unitario REAL, subtotal REAL, bultos INTEGER, unidades_sueltas INTEGER);
    CREATE TABLE entrega (id_entrega INTEGER PRIMARY KEY AUTOINCREMENT, id_repartidor INTEGER, fecha_creacion TEXT, fecha_salida TEXT, estado TEXT, observaciones TEXT, id_usuario_creacion INTEGER);
    CREATE TABLE entrega_pedido (id_entrega_pedido INTEGER PRIMARY KEY AUTOINCREMENT, id_entrega INTEGER, id_venta INTEGER, estado_pedido_entrega TEXT, motivo_rechazo TEXT, fecha_actualizacion TEXT);
    CREATE TABLE remito (id_remito INTEGER PRIMARY KEY AUTOINCREMENT, numero_remito TEXT, id_entrega INTEGER, id_venta INTEGER, fecha_emision TEXT, estado TEXT, observaciones TEXT);
    CREATE TABLE detalle_remito (id_detalle_remito INTEGER PRIMARY KEY AUTOINCREMENT, id_remito INTEGER, id_producto INTEGER, descripcion TEXT, cantidad INTEGER, bultos INTEGER);
");

// Insertar datos de prueba
$pdoTest->exec("
    INSERT INTO zona VALUES (1, 'Ruta Saavedra', 'Zona Saavedra'), (2, 'Ruta Tornquist', 'Zona Tornquist');
    INSERT INTO cliente VALUES (1, 'Almacén Don Juan', 'Don Juan SRL', '30-11111111-1', 'San Martín 100', 'Saavedra', '123', 1, 'activo'), (2, 'Super Tornquist', 'Tornquist SA', '30-22222222-2', 'Belgrano 200', 'Tornquist', '456', 2, 'activo');
    INSERT INTO usuario VALUES (1, 'Diego Admin', 'diego', '123', 'admin', 'activo'), (3, 'Matías Repartidor', 'matias', '123', 'repartidor', 'activo');
    INSERT INTO producto VALUES (1, 'ACE-01', 'Aceite Girasol 1.5L', 'Caja x 12', 18500, 1, 100, 'activo');
    INSERT INTO venta VALUES (1, 1, 1, '2026-08-31 10:00:00', 92500, 5, 4, 'confirmada', 'Entregar temprano'), (2, 2, 1, '2026-08-31 11:00:00', 185000, 10, 0, 'confirmada', 'Cobrar');
    INSERT INTO detalle_venta VALUES (1, 1, 1, 5, 18500, 92500, 5, 4), (2, 2, 1, 10, 18500, 185000, 10, 0);
");

// Inyectar PDO de prueba
Conexion::setInstancia($pdoTest);

$entregaRepo = new EntregaRepository($pdoTest);
$remitoRepo = new RemitoRepository($pdoTest);
$service = new EntregaService($entregaRepo, $remitoRepo);

// TEST 1: HU #1 — Obtener pedidos confirmados pendientes con bultos y sueltos
echo "--- TEST HU #1: Visualización de pedidos pendientes y desglose físico ---\n";
$pedidosPendientes = $service->obtenerPedidosPendientes();
assertCustom(count($pedidosPendientes) === 2, "Debe listar los 2 pedidos confirmados disponibles");
assertCustom($pedidosPendientes[0]['cliente_nombre'] === 'Almacén Don Juan', "El primer pedido corresponde al cliente Don Juan");
assertCustom($pedidosPendientes[0]['total_bultos'] == 5, "El primer pedido tiene 5 bultos cerrados calculados");
assertCustom($pedidosPendientes[0]['total_sueltos'] == 4, "El primer pedido tiene 4 artículos sueltos calculados");

// TEST 2: HU #1 — Filtro por Zona
$pedidosSaavedra = $service->obtenerPedidosPendientes(1);
assertCustom(count($pedidosSaavedra) === 1, "Filtrar por Zona 1 debe devolver 1 solo pedido");
assertCustom($pedidosSaavedra[0]['zona_nombre'] === 'Ruta Saavedra', "El pedido filtrado es de Ruta Saavedra");

// TEST 3: HU #2 — Validación: no permite entrega sin pedidos
echo "\n--- TEST HU #2: Armado de entrega y emisión de remitos ---\n";
try {
    $service->armarEntrega(3, [], '2026-08-31', 1);
    assertCustom(false, "Debe lanzar excepción si la lista de pedidos está vacía");
} catch (\Exception $e) {
    assertCustom(str_contains($e->getMessage(), "al menos un pedido"), "Valida correctamente que se seleccione al menos un pedido");
}

// TEST 4: HU #2 — Validación: no permite entrega sin repartidor
try {
    $service->armarEntrega(0, [1], '2026-08-31', 1);
    assertCustom(false, "Debe lanzar excepción si no hay repartidor");
} catch (\Exception $e) {
    assertCustom(str_contains($e->getMessage(), "repartidor válido"), "Valida correctamente que se asigne un repartidor");
}

// TEST 5: HU #2 — Armado exitoso de entrega con 2 pedidos y emisión de remitos correlativos
$resultado = $service->armarEntrega(3, [1, 2], '2026-08-31', 1, 'Viaje prueba');
assertCustom($resultado['id_entrega'] > 0, "La entrega se creó con un ID válido (#{$resultado['id_entrega']})");
assertCustom($resultado['estado'] === 'en_preparacion', "La entrega nace en estado 'en_preparacion'");
assertCustom(count($resultado['remitos']) === 2, "Se emitieron exactamente 2 remitos oficiales");
assertCustom($resultado['remitos'][0]['numero_remito'] === 'R-0001-00000001', "El primer remito tiene numeración R-0001-00000001");
assertCustom($resultado['remitos'][1]['numero_remito'] === 'R-0001-00000002', "El segundo remito es correlativo: R-0001-00000002");

// TEST 6: HU #1 post-entrega — Los pedidos asignados ya no deben aparecer como pendientes
$pedidosPost = $service->obtenerPedidosPendientes();
assertCustom(count($pedidosPost) === 0, "Los pedidos asignados ya NO figuran en la lista de pendientes de despacho");

echo "\n====================================================\n";
echo "📊 RESUMEN: {$testsPasados} PASADOS / {$testsFallados} FALLADOS\n";
echo "====================================================\n";

if ($testsFallados > 0) {
    exit(1);
}
