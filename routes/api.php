<?php

use Illuminate\Support\Facades\Route;
use App\Entregas\Controllers\EntregaController;
use App\ConciliacionBancaria\Controllers\ConciliacionController;
use App\Cobros\Controllers\CobroController;

// Si se ejecuta bajo Laravel Framework
if (class_exists('Illuminate\Support\Facades\Route')) {
    Route::prefix('v1')->group(function () {
        // --- ENTREGAS (Grupo 2 - Estefanía Gianovich) ---
        Route::get('/entregas/pendientes-despacho', [EntregaController::class, 'pedidosPendientes']);
        Route::post('/entregas', [EntregaController::class, 'crearEntrega']);
        Route::get('/entregas', [EntregaController::class, 'listar']);
        Route::get('/entregas/{id}', [EntregaController::class, 'detalle']);
        Route::get('/remitos/{id}', [EntregaController::class, 'verRemito']);
        Route::get('/zonas', [EntregaController::class, 'zonas']);
        Route::get('/repartidores', [EntregaController::class, 'repartidores']);

        // --- CONCILIACIÓN BANCARIA (Grupo 2 - Sofía) ---
        Route::get('/conciliacion', [ConciliacionController::class, 'index']);
        Route::get('/conciliacion/{id}', [ConciliacionController::class, 'show']);
        Route::post('/conciliacion', [ConciliacionController::class, 'store']);
        Route::patch('/conciliacion/{id}/conciliar', [ConciliacionController::class, 'conciliarAutomatico']);
        Route::post('/conciliacion/movimientos/{id}/conciliar-manual', [ConciliacionController::class, 'conciliarManual']);
        Route::patch('/conciliacion/{id}/cerrar', [ConciliacionController::class, 'cerrar']);
        
        // --- COBROS (Grupo 2 - Tomás) ---
        Route::post('/cobros', [CobroController::class, 'store']);
        Route::get('/cobros/clientes/{idCliente}/saldo', [CobroController::class, 'saldo']);
    });
}

// Si se ejecuta con el Router nativo / standalone
if (isset($router)) {
    // --- ENTREGAS ---
    $router->get('/api/v1/entregas/pendientes-despacho', [EntregaController::class, 'pedidosPendientes']);
    $router->post('/api/v1/entregas', [EntregaController::class, 'crearEntrega']);
    $router->get('/api/v1/entregas', [EntregaController::class, 'listar']);
    $router->get('/api/v1/entregas/{id}', [EntregaController::class, 'detalle']);
    $router->get('/api/v1/remitos/{id}', [EntregaController::class, 'verRemito']);
    $router->get('/api/v1/zonas', [EntregaController::class, 'zonas']);
    $router->get('/api/v1/repartidores', [EntregaController::class, 'repartidores']);

    // --- CONCILIACIÓN BANCARIA ---
    if (class_exists(ConciliacionController::class)) {
        $router->get('/api/v1/conciliacion', [ConciliacionController::class, 'index']);
        $router->get('/api/v1/conciliacion/{id}', [ConciliacionController::class, 'show']);
        $router->post('/api/v1/conciliacion', [ConciliacionController::class, 'store']);
        $router->patch('/api/v1/conciliacion/{id}/conciliar', [ConciliacionController::class, 'conciliarAutomatico']);
        $router->post('/api/v1/conciliacion/movimientos/{id}/conciliar-manual', [ConciliacionController::class, 'conciliarManual']);
        $router->patch('/api/v1/conciliacion/{id}/cerrar', [ConciliacionController::class, 'cerrar']);
    }

    // --- COBROS ---
    if (class_exists(CobroController::class)) {
        $router->post('/api/v1/cobros', [CobroController::class, 'store']);
        $router->get('/api/v1/cobros/clientes/{idCliente}/saldo', [CobroController::class, 'saldo']);
    }
}
