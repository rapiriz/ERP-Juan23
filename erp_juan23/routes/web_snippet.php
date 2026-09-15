<?php

// ⚠️ SNIPPET: copiá este bloque dentro de tu routes/web.php real,
// dentro del grupo de rutas que ya tengas protegido con 'auth' (o agregá el
// middleware 'auth' si no tenés uno).

use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    // Vistas de las páginas ya existentes (API-driven)
    Route::view('/sugerencias-reposicion', 'sugerencias-reposicion')->name('sugerencias-reposicion.index');
    Route::view('/vencimientos', 'vencimientos')->name('vencimientos.index');

    // Dashboard con gráficos
    Route::get('/dashboard-reposicion', [\App\Http\Controllers\DashboardReposicionController::class, 'index'])
        ->name('dashboard-reposicion.index');

    // CRUD web de lotes de producto
    Route::prefix('lotes')->name('lotes.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ProductoLoteController::class, 'index'])->name('index');
        Route::get('/crear', [\App\Http\Controllers\ProductoLoteController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\ProductoLoteController::class, 'store'])->name('store');
        Route::get('/{id}/editar', [\App\Http\Controllers\ProductoLoteController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\ProductoLoteController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\ProductoLoteController::class, 'destroy'])->name('destroy');
    });
});
