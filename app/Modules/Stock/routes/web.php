<?php

use App\Modules\Stock\Controllers\LoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.current', 'role:administrativo,contador,repartidor'])->group(function () {
    Route::get('/inventario/lotes', [LoteController::class, 'page'])->name('lotes.index');
    Route::get('/inventario/lotes/datos', [LoteController::class, 'index'])->name('lotes.data');
    Route::get('/inventario/lotes/{id}/movimientos', [LoteController::class, 'history'])->whereNumber('id')->name('lotes.history');
    Route::middleware('role:administrativo')->group(function () {
        Route::post('/inventario/lotes', [LoteController::class, 'store'])->name('lotes.store');
        Route::post('/inventario/lotes/{id}/adoptar', [LoteController::class, 'adopt'])->whereNumber('id')->name('lotes.adopt');
    });
});
