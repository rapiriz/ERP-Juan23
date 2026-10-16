<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\Api\VentaApiController;

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

// Módulo de Ventas
Route::get('/ventas/productos-simulados', [VentaController::class, 'obtenerProductosSimulados']);
Route::post('/ventas', [VentaController::class, 'store']);

// ==========================================
// MÓDULO: VENTAS - consultar, modificar, anular y cambiar estado
// Controlador: app/Http/Controllers/Api/VentaApiController.php
// Guía completa: docs/VENTAS-BACKEND.md
//
// whereNumber('id') hace que {id} solo acepte números: así "/ventas/estados"
// o "/ventas/productos-simulados" nunca se confunden con el id de una venta.
// ==========================================
Route::prefix('ventas')->controller(VentaApiController::class)->name('api.ventas.')->group(function () {
    Route::get('/', 'index')->name('index');                                              // Lista con filtros y paginación (antes devolvía HTML por error)
    Route::get('/estados', 'estados')->name('estados');                                   // Catálogo de estados y sus reglas
    Route::get('/productos', 'productos')->name('productos');                             // Catálogo para "Agregar producto" al modificar
    Route::get('/{id}', 'show')->whereNumber('id')->name('show');                         // Una venta con detalle, botones e historial
    Route::put('/{id}', 'update')->whereNumber('id')->name('update');                     // Botón "Modificar"
    Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');                // Botón "Eliminar" (anula, no borra)
    Route::post('/{id}/restaurar', 'restaurar')->whereNumber('id')->name('restaurar');    // Deshace una anulación
    Route::patch('/{id}/estado', 'cambiarEstado')->whereNumber('id')->name('estado');     // Avanza de fase
});
