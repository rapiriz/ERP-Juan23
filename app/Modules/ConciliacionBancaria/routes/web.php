<?php

use App\Modules\ConciliacionBancaria\Controllers\Api\V1\ConciliacionApiController;
use App\Modules\ConciliacionBancaria\Controllers\CobranzaErpController;
use App\Modules\ConciliacionBancaria\Controllers\ConciliacionController;
use App\Modules\ConciliacionBancaria\Controllers\MovimientoBancarioController;
use App\Modules\Shared\Http\Middleware\G2Access;
use App\Modules\Shared\Http\Middleware\G2SchemaReady;
use Illuminate\Support\Facades\Route;

// Rutas protegidas por sesión de G2
Route::middleware(['auth', 'session.current', G2Access::class.':admin,contador,administrativo', G2SchemaReady::class.':conciliacion'])->group(function () {
    Route::prefix('g2/api/conciliacion')->group(function () {
        Route::get('/propuestas', [ConciliacionController::class, 'propuestas']);
        Route::post('/auto-matching', [ConciliacionController::class, 'autoMatching']);
        Route::post('/manual', [ConciliacionController::class, 'conciliarManual']);
        Route::patch('/movimientos/{id}/estado', [ConciliacionController::class, 'cambiarEstado'])->whereNumber('id');
    });
});

// APIs públicas y de integración frontend de Conciliación Bancaria (HU-01, HU-02, HU-03)
Route::middleware(['web'])->group(function () {
    // Pantalla de Split Screen
    Route::get('/conciliacion', fn() => view('conciliacion.split-screen'))->name('conciliacion.index');
    Route::get('/conciliacion/split', fn() => view('conciliacion.split-screen'))->name('conciliacion.split');

    // Movimientos bancarios (HU-01)
    Route::get('/api/movimientos-bancarios', [MovimientoBancarioController::class, 'index']);
    Route::get('/api/movimientos-bancarios/{id}', [MovimientoBancarioController::class, 'show'])->whereNumber('id');
    Route::post('/api/extractos/importar', [MovimientoBancarioController::class, 'importar']);

    // Cobranzas ERP desde RENDICION_COBRO + RENDICION
    Route::get('/api/cobranzas-erp', [CobranzaErpController::class, 'index']);
    Route::get('/api/cobranzas-erp/{id}', [CobranzaErpController::class, 'show'])->whereNumber('id');

    // Matching y excepciones (HU-02 y HU-03)
    Route::get('/api/conciliacion/propuestas', [ConciliacionController::class, 'propuestas']);
    Route::post('/api/conciliacion/auto-matching', [ConciliacionController::class, 'autoMatching']);
    Route::post('/api/conciliacion/manual', [ConciliacionController::class, 'conciliarManual']);
    Route::patch('/api/conciliacion/movimientos/{id}/estado', [ConciliacionController::class, 'cambiarEstado'])->whereNumber('id');

    // Compatibilidad API stateless v1
    Route::get('/api/v1/conciliacion/movimientos', [ConciliacionApiController::class, 'movimientos']);
});
