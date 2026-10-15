<?php

use Illuminate\Support\Facades\Route;
use App\Entregas\Controllers\EntregaController;
use App\ConciliacionBancaria\Controllers\ConciliacionController;
use App\ConciliacionBancaria\Controllers\ChequeController;
use App\Caja\Controllers\CajaController;
use App\Shared\Auth\Controllers\DevLoginController;
use App\Cobros\Controllers\CobroController;

// Login de desarrollo (solo se registra en entorno local)
if (app()->environment('local')) {
    Route::post('/auth/dev-login', [DevLoginController::class, 'login']);
}

Route::prefix('v1')->group(function () {

    // --- CONCILIACIÓN BANCARIA ---
    Route::get('/conciliacion', [ConciliacionController::class, 'index']);
    Route::get('/conciliacion/{id}', [ConciliacionController::class, 'show']);
    Route::post('/conciliacion', [ConciliacionController::class, 'store']);
    Route::patch('/conciliacion/{id}/conciliar', [ConciliacionController::class, 'conciliarAutomatico']);
    Route::post('/conciliacion/movimientos/{id}/conciliar-manual', [ConciliacionController::class, 'conciliarManual']);
    Route::patch('/conciliacion/{id}/cerrar', [ConciliacionController::class, 'cerrar']);

    // --- CHEQUES ---
    Route::get('/cheques', [ChequeController::class, 'index']);
    Route::get('/cheques/{id}', [ChequeController::class, 'show']);
    Route::patch('/cheques/{id}/depositar', [ChequeController::class, 'depositar']);
    Route::patch('/cheques/{id}/rechazar', [ChequeController::class, 'rechazar']);

    // --- CAJA (protegida con Sanctum) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/caja', [CajaController::class, 'listar']);
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

    // --- ENTREGAS ---
    Route::get('/entregas/pendientes-despacho', [EntregaController::class, 'pedidosPendientes']);
    Route::post('/entregas', [EntregaController::class, 'crearEntrega']);
    Route::get('/entregas', [EntregaController::class, 'listar']);
    Route::get('/entregas/{id}', [EntregaController::class, 'detalle']);
    Route::get('/remitos/{id}', [EntregaController::class, 'verRemito']);
    Route::get('/zonas', [EntregaController::class, 'zonas']);
    Route::get('/repartidores', [EntregaController::class, 'repartidores']);

    // --- COBROS ---
    Route::post('/cobros', [CobroController::class, 'store']);
    Route::get('/cobros/clientes/{idCliente}/saldo', [CobroController::class, 'saldo']);
});
