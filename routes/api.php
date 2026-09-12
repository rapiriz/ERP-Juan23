<?php

use Illuminate\Support\Facades\Route;
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