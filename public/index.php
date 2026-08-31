<?php
/**
 * Punto de entrada único del backend (API REST ERP Distribuidora Juan XXIII)
 */

// Autoload simple por namespaces basados en /src
spl_autoload_register(function ($class) {
    $prefix = '';
    $baseDir = __DIR__ . '/../src/';

    $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use Shared\Http\Router;
use Entregas\Controllers\EntregaController;

// Headers CORS para permitir pruebas desde frontend
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$router = new Router();

// ==========================================
// RUTAS MÓDULO ENTREGAS (Grupo 2)
// ==========================================
// HU #1: Pedidos pendientes de despacho
$router->get('/api/v1/entregas/pendientes-despacho', [EntregaController::class, 'pedidosPendientes']);

// HU #2: Crear entrega agrupando pedidos y emitiendo remitos
$router->post('/api/v1/entregas', [EntregaController::class, 'crearEntrega']);

// Listado y detalle de entregas
$router->get('/api/v1/entregas', [EntregaController::class, 'listar']);
$router->get('/api/v1/entregas/{id}', [EntregaController::class, 'detalle']);

// Consulta de Remitos
$router->get('/api/v1/remitos/{id}', [EntregaController::class, 'verRemito']);

// Endpoints auxiliares
$router->get('/api/v1/zonas', [EntregaController::class, 'zonas']);
$router->get('/api/v1/repartidores', [EntregaController::class, 'repartidores']);

// Despachar la petición
$router->despachar();
