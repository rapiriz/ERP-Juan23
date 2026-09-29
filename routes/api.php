<?php

use Illuminate\Support\Facades\Route;
use App\Entregas\Controllers\EntregaController;
use App\ConciliacionBancaria\Controllers\ConciliacionController;
use App\Cobros\Controllers\CobroController;
use App\Rendiciones\Controllers\RendicionController;

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

    // --- RENDICIONES (Grupo 2 - Nicolás) ---
    Route::get('/rendiciones', [RendicionController::class, 'index']);
    Route::get('/rendiciones/historial', [RendicionController::class, 'index']);
    Route::get('/rendiciones/{id}', [RendicionController::class, 'show']);
    Route::post('/rendiciones', [RendicionController::class, 'store']);
    Route::post('/rendiciones/cobros', [RendicionController::class, 'agregarCobroDirecto']);
    Route::post('/rendiciones/{id}/cobros', [RendicionController::class, 'agregarCobro']);
    Route::post('/rendiciones/devoluciones', [RendicionController::class, 'registrarDevolucionDirecta']);
    Route::post('/rendiciones/{id}/devoluciones', [RendicionController::class, 'registrarDevolucion']);
    Route::post('/rendiciones/diferencias', [RendicionController::class, 'registrarDiferenciaDirecta']);
    Route::post('/rendiciones/{id}/diferencias', [RendicionController::class, 'registrarDiferencia']);
    Route::post('/rendiciones/validar', [RendicionController::class, 'validarDirecta']);
    Route::patch('/rendiciones/{id}/cerrar', [RendicionController::class, 'cerrar']);
    Route::patch('/rendiciones/{id}/revisar', [RendicionController::class, 'revisar']);
});

