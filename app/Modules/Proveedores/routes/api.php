<?php

use App\Modules\Login\Middleware\AuthenticateApiReadRequest;
use App\Modules\Proveedores\Controllers\SupplierPerformanceController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/reportes/proveedores-cumplimiento', [SupplierPerformanceController::class, 'index'])
    ->middleware([AuthenticateApiReadRequest::class, 'api.audience:administrativo,contador', 'throttle:60,1']);
