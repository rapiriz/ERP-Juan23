<?php

use App\Modules\NotificacionesTransferencia\Controllers\TransferNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.current', 'role:administrativo,contador'])->prefix('transferencias')->name('transferencias.')->group(function (): void {
    Route::get('/', [TransferNotificationController::class, 'index'])->name('index');
    Route::get('/{transferencia}/comprobante', [TransferNotificationController::class, 'download'])->whereNumber('transferencia')->name('download');
    Route::middleware(['role:administrativo', 'throttle:20,1'])->group(function (): void {
        Route::post('/', [TransferNotificationController::class, 'store'])->name('store');
        Route::patch('/{transferencia}/revision', [TransferNotificationController::class, 'review'])->whereNumber('transferencia')->name('review');
    });
});
