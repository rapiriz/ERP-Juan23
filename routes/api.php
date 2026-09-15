<?php

use Illuminate\Support\Facades\Route;
use App\Entregas\Controllers\EntregaController;
use App\ConciliacionBancaria\Controllers\ConciliacionController;
use App\ConciliacionBancaria\Controllers\ChequeController;
use App\Caja\Controllers\CajaController;

Route::prefix('v1')->group(function () {
    Route::get('/conciliacion', [ConciliacionController::class, 'index']);
    Route::get('/conciliacion/{id}', [ConciliacionController::class, 'show']);
    Route::post('/conciliacion', [ConciliacionController::class, 'store']);
    Route::patch('/conciliacion/{id}/conciliar', [ConciliacionController::class, 'conciliarAutomatico']);
    Route::post('/conciliacion/movimientos/{id}/conciliar-manual', [ConciliacionController::class, 'conciliarManual']);
    Route::patch('/conciliacion/{id}/cerrar', [ConciliacionController::class, 'cerrar']);

    Route::get('/cheques', [ChequeController::class, 'index']);
    Route::get('/cheques/{id}', [ChequeController::class, 'show']);
    Route::patch('/cheques/{id}/depositar', [ChequeController::class, 'depositar']);
    Route::patch('/cheques/{id}/rechazar', [ChequeController::class, 'rechazar']);

    // --- Caja --- protegidas con sesión real (Sanctum) ---
    // OJO con el orden: las rutas más específicas van antes de /caja/{fecha},
    // si no Laravel interpreta "cierres" o "movimiento" como un valor de {fecha}.
    Route::post('/caja/apertura', [CajaController::class, 'abrir']);
    Route::get('/caja/cierres', [CajaController::class, 'historialCierres']);
    Route::get('/caja/cierres/{id}', [CajaController::class, 'detalleCierre']);
    Route::post('/caja/movimiento', [CajaController::class, 'registrarMovimiento']);
    Route::get('/caja/movimiento/{id}', [CajaController::class, 'detalleMovimiento']);
    Route::patch('/caja/movimiento/{id}', [CajaController::class, 'modificarMovimiento']);
    Route::patch('/caja/{id}/cerrar', [CajaController::class, 'cerrar']);
    Route::get('/caja/{fecha}/{id_usuario}', [CajaController::class, 'porUsuarioYFecha']);
    Route::get('/caja/{fecha}', [CajaController::class, 'porFecha']);
});
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
