<?php

use App\Modules\Proveedores\Controllers\SupplierPerformanceController;
use Illuminate\Support\Facades\Route;

Route::get('/proveedores/estadisticas', [SupplierPerformanceController::class, 'index'])
    ->middleware(['auth', 'session.current', 'role:administrativo,contador'])->name('supplier-performance.index');
