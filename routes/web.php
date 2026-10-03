<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/promociones', [HomeController::class, 'promociones'])->name('promociones');

require __DIR__.'/ventas.php';
//__DIR__ representa la carpeta actual (routes/).
// El require "enchufa" el archivo ventas.php, que define todas las rutas del módulo de ventas.
