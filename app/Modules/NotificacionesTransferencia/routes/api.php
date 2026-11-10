<?php

use App\Modules\Login\Middleware\AuthenticateApiSessionRequest;
use App\Modules\NotificacionesTransferencia\Controllers\TransferNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/transferencias')->middleware([AuthenticateApiSessionRequest::class, 'api.audience:administrativo,contador', 'throttle:60,1'])->group(function (): void {
    Route::get('/', [TransferNotificationController::class, 'index']);
    Route::get('/{transferencia}', [TransferNotificationController::class, 'show'])->whereNumber('transferencia');
    Route::get('/{transferencia}/comprobante', [TransferNotificationController::class, 'download'])->whereNumber('transferencia');
    Route::post('/', [TransferNotificationController::class, 'store'])->middleware('api.user:administrativo');
    Route::patch('/{transferencia}/revision', [TransferNotificationController::class, 'review'])->whereNumber('transferencia')->middleware('api.user:administrativo');
});
