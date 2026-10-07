<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PromocionController;

// ==========================================
// MÓDULO: PRODUCTOS
// ==========================================
Route::post('/productos', [ProductoController::class, 'store']);                   // Registra un nuevo producto[cite: 16]
Route::get('/productos', [ProductoController::class, 'index']);                    // Lista productos con filtros[cite: 16]
Route::get('/productos/{id}', [ProductoController::class, 'show']);                // Obtiene el detalle completo de un producto[cite: 16]
Route::put('/productos/{id}', [ProductoController::class, 'update']);              // Modifica los datos de un producto[cite: 16]
Route::patch('/productos/{id}/estado', [ProductoController::class, 'updateEstado']); // Activa o desactiva un producto[cite: 16]

// ==========================================
// MÓDULO: PROMOCIONES (Trabajo previo)
// ==========================================
Route::get('/promociones', [PromocionController::class, 'index']);          // Listar todas
Route::post('/promociones', [PromocionController::class, 'store']);         // Crear nueva
Route::get('/promociones/{id}', [PromocionController::class, 'show']);      // Ver detalle de una
Route::put('/promociones/{id}', [PromocionController::class, 'update']);    // Editar una existente
Route::delete('/promociones/{id}', [PromocionController::class, 'destroy']); // Eliminar una promocion
Route::patch('/promociones/{id}/estado', [PromocionController::class, 'updateEstado']); // Reactivación / Cambio de estado