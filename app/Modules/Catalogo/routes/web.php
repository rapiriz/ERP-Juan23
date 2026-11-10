<?php

use App\Modules\Catalogo\Controllers\CatalogOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/catalogo/carrito', [CatalogOrderController::class, 'cart'])->name('catalogo.cart');
Route::post('/catalogo/pedidos', [CatalogOrderController::class, 'store'])->middleware('throttle:5,1,catalog-orders')->name('catalogo.orders.store');
Route::get('/catalogo/pedido/{token}', [CatalogOrderController::class, 'receipt'])->where('token', '[a-f0-9]{64}')->middleware('throttle:30,1,catalog-receipts')->name('catalogo.orders.receipt');
Route::middleware(['auth', 'session.current', 'role:administrativo'])->group(function () {
    Route::get('/pedidos-catalogo', [CatalogOrderController::class, 'index'])->name('catalogo.orders.index');
    Route::get('/pedidos-catalogo/{id}', [CatalogOrderController::class, 'show'])->whereNumber('id')->name('catalogo.orders.show');
    Route::post('/pedidos-catalogo/{id}/revisar', [CatalogOrderController::class, 'review'])->whereNumber('id')->name('catalogo.orders.review');
});
