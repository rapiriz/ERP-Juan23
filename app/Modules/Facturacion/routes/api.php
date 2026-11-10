<?php

use App\Modules\Facturacion\Controllers\FiscalStatusController;
use App\Modules\Login\Middleware\AuthenticateApiReadRequest;
use Illuminate\Support\Facades\Route;

Route::get('/v1/fiscal/estado', FiscalStatusController::class)->middleware([
    AuthenticateApiReadRequest::class, 'api.audience:administrativo,contador', 'throttle:60,1',
]);
