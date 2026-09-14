<?php

use Illuminate\Support\Facades\Route;
use App\ConciliacionBancaria\Controllers\ConciliacionWebController;
use App\Caja\Controllers\CajaWebController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('conciliacion')->name('conciliacion.')->group(function () {
    Route::get('/', [ConciliacionWebController::class, 'index'])->name('index');
    Route::get('/nuevo', [ConciliacionWebController::class, 'create'])->name('create');
    Route::post('/', [ConciliacionWebController::class, 'store'])->name('store');
    Route::get('/{id}', [ConciliacionWebController::class, 'show'])->name('show');
    Route::post('/{id}/conciliar', [ConciliacionWebController::class, 'conciliarAutomatico'])->name('conciliar');
    Route::post('/movimientos/{id}/conciliar-manual', [ConciliacionWebController::class, 'conciliarManual'])->name('conciliar-manual');
    Route::patch('/{id}/cerrar', [ConciliacionWebController::class, 'cerrar'])->name('cerrar');
});

Route::prefix('caja')->name('caja.')->group(function () {
    Route::get('/', [CajaWebController::class, 'diaActual'])->name('dia');
    Route::post('/apertura', [CajaWebController::class, 'abrir'])->name('abrir');
    Route::post('/{id}/movimiento', [CajaWebController::class, 'registrarMovimiento'])->name('movimiento');
    Route::patch('/{id}/cerrar', [CajaWebController::class, 'cerrar'])->name('cerrar');
    Route::get('/cierres', [CajaWebController::class, 'cierresIndex'])->name('cierres.index');
    Route::get('/cierres/{id}', [CajaWebController::class, 'cierresShow'])->name('cierres.show');
    Route::get('/pendiente/{id}', [CajaWebController::class, 'mostrarCaja'])->name('pendiente');
});