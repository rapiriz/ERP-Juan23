<?php

use Illuminate\Support\Facades\Route;
use App\ConciliacionBancaria\Controllers\ConciliacionController;
use App\Cobros\Controllers\CobroController;

Route::prefix('v1')->group(function () {
// --- CONCILIACIÓN BANCARIA ---
    Route::get('/conciliacion', [ConciliacionController::class, 'index']);
    Route::get('/conciliacion/{id}', [ConciliacionController::class, 'show']);
    Route::post('/conciliacion', [ConciliacionController::class, 'store']);
    Route::patch('/conciliacion/{id}/conciliar', [ConciliacionController::class, 'conciliarAutomatico']);
    Route::post('/conciliacion/movimientos/{id}/conciliar-manual', [ConciliacionController::class, 'conciliarManual']);
    Route::patch('/conciliacion/{id}/cerrar', [ConciliacionController::class, 'cerrar']);
    
    // --- COBROS (Grupo 2) ---
    Route::post('/cobros', [CobroController::class, 'store']);
    Route::get('/cobros/clientes/{idCliente}/saldo', [CobroController::class, 'saldo']);
});