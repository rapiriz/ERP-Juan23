<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductoController;

// ==========================================
// MÓDULO: PRODUCTOS
// ==========================================
Route::post('/productos', [ProductoController::class, 'store']);                   // Registra un nuevo producto[cite: 16]
Route::get('/productos', [ProductoController::class, 'index']);                    // Lista productos con filtros[cite: 16]
Route::get('/productos/{id}', [ProductoController::class, 'show']);                // Obtiene el detalle completo de un producto[cite: 16]
Route::put('/productos/{id}', [ProductoController::class, 'update']);              // Modifica los datos de un producto[cite: 16]
Route::patch('/productos/{id}/estado', [ProductoController::class, 'updateEstado']); // Activa o desactiva un producto[cite: 16]

// ==========================================
// MÓDULO: PROMOCIONES → sus rutas están en app/Modules/Promociones/routes/api.php
// ==========================================

// ==========================================
// MÓDULO: VENTAS → sus rutas están en app/Modules/Ventas/routes/api.php
// (las carga solo App\Providers\ModuleServiceProvider)
// ==========================================
