<?php

use App\Modules\Login\Middleware\AuthenticateApiReadRequest;
use App\Modules\Stock\Controllers\LoteController;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateApiReadRequest::class, 'api.audience:administrativo,contador,repartidor', 'throttle:60,1'])->group(function () {
    Route::get('/v1/lotes', [LoteController::class, 'index']);
    Route::get('/vencimientos/proximos', [LoteController::class, 'index'])->defaults('estado', 'proximo');
});
Route::post('/v1/lotes', [LoteController::class, 'store'])->middleware(['api.access', 'api.write.user:administrativo', 'throttle:30,1']);
