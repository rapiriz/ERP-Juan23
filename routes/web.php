<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CuentaCorrienteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VentaController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/promociones', [HomeController::class, 'promociones'])->name('promociones');
Route::get('/ventas', [VentaController::class, 'index'])->name('ventas');
Route::get('/saldo', [CuentaCorrienteController::class, 'index'])->name('saldo');
