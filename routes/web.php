<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/promociones', [HomeController::class, 'promociones'])->name('promociones');

// Rutas del módulo Ventas (/ventas, /historial-ventas): app/Modules/Ventas/routes/web.php
// Rutas del módulo Cuenta Corriente (/saldo): app/Modules/CuentaCorriente/routes/web.php
