<?php

use App\Modules\CuentaCorriente\Controllers\CuentaCorrienteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Módulo Cuenta Corriente (G4)
|--------------------------------------------------------------------------
| Las carga solo App\Providers\ModuleServiceProvider (middleware "web").
| Antes estaban en routes/web.php: se mudaron sin cambiar URL ni nombre.
*/

Route::middleware(['auth', 'session.current', 'role:administrativo,contador'])->group(function (): void {
    Route::get('/saldo/exportar/pdf', [CuentaCorrienteController::class, 'exportPdf'])->name('saldo.pdf');
    Route::get('/saldo', [CuentaCorrienteController::class, 'index'])->name('saldo');
    Route::post('/saldo/cobros', [CuentaCorrienteController::class, 'registrarCobro'])->middleware('role:administrativo')->name('saldo.cobros');
    Route::post('/saldo/{cliente}/movimientos', [CuentaCorrienteController::class, 'registrarMovimiento'])->middleware('role:administrativo')->name('saldo.movimientos');
});
