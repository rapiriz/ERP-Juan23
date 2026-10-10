<?php

/**
 * PASO 2 - RUTAS (ENDPOINTS) DEL MÓDULO VENTAS
 * Este archivo se carga desde routes/api.php, por eso todas las URLs
 * empiezan automáticamente con "/api" (ej: /api/ventas).
 */

use App\Modules\Venta\Controllers\VentasController;
use Illuminate\Support\Facades\Route;

// GET  /api/ventas       -> lista todas las ventas con sus detalles
Route::get('/ventas', [VentasController::class, 'index']);

// GET  /api/ventas/{id}  -> trae una sola venta. whereNumber evita que
// "/ventas/productos-simulados" se confunda con un id.
Route::get('/ventas/{id}', [VentasController::class, 'show'])->whereNumber('id');

// POST /api/ventas       -> registra una venta nueva (lo usa el botón "Confirmar cobro")
Route::post('/ventas', [VentasController::class, 'store']);
