<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/promociones', [HomeController::class, 'promociones'])->name('promociones');

// Carga las rutas web del POS; allí /ventas apunta al controlador backend real.
require __DIR__.'/ventas.php';
