<?php

use Illuminate\Support\Facades\Route;
use App\ConciliacionBancaria\Controllers\ConciliacionWebController;

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