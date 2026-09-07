<?php
/**
 * Rutas de la API (Laravel / Monolito Modular)
 */

use App\Entregas\Controllers\EntregaController;

// ====================================================================
// MÓDULO DE ENTREGAS (GRUPO 2 - Estefania Gianovich)
// ====================================================================

// HU #1: Pedidos confirmados pendientes de despacho
$router->get('/api/v1/entregas/pendientes-despacho', [EntregaController::class, 'pedidosPendientes']);

// HU #2: Crear entrega agrupando pedidos y emitiendo remitos
$router->post('/api/v1/entregas', [EntregaController::class, 'crearEntrega']);

// Listado y detalle de viajes de entrega
$router->get('/api/v1/entregas', [EntregaController::class, 'listar']);
$router->get('/api/v1/entregas/{id}', [EntregaController::class, 'detalle']);

// Consulta de Remitos
$router->get('/api/v1/remitos/{id}', [EntregaController::class, 'verRemito']);

// Endpoints auxiliares
$router->get('/api/v1/zonas', [EntregaController::class, 'zonas']);
$router->get('/api/v1/repartidores', [EntregaController::class, 'repartidores']);
