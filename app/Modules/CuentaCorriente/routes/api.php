<?php

use App\Http\Middleware\IdentidadUsuario;
use App\Modules\CuentaCorriente\Controllers\Api\V1\FinancialReadController;
use App\Modules\CuentaCorriente\Controllers\ClientPaymentController;
use App\Modules\CuentaCorriente\Controllers\CuentaCorrienteController;
use App\Modules\Login\Middleware\AuthenticateApiReadRequest;
use App\Modules\Login\Middleware\AuthenticateApiSessionRequest;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Módulo Cuenta Corriente
|--------------------------------------------------------------------------
| Las carga App\Providers\ModuleServiceProvider, con prefijo /api y middleware "api".
*/

Route::post('/cuenta-corriente/cobros', [CuentaCorrienteController::class, 'registrarCobro'])
    ->middleware([AuthenticateApiSessionRequest::class, 'api.audience:administrativo', IdentidadUsuario::class])
    ->name('api.cuenta-corriente.cobros');

Route::post('/v1/finanzas/clientes/{cliente}/pagos', [ClientPaymentController::class, 'store'])
    ->whereNumber('cliente')->middleware([AuthenticateApiSessionRequest::class, 'api.user:administrativo', 'throttle:20,1']);

// Fuente financiera de G4. Las rutas de Clientes de G1 consumen este contrato.
Route::prefix('v1/finanzas')->middleware([
    AuthenticateApiReadRequest::class,
    'api.audience:administrativo,contador,repartidor', 'throttle:60,1',
])->controller(FinancialReadController::class)
    ->group(function (): void {
        Route::get('clientes/deudores', 'debtors')->middleware('api.audience:administrativo,contador');
        Route::get('clientes/{id}/saldo', 'balance')->whereNumber('id');
        Route::get('facturas/pendientes', 'invoices')->middleware('api.audience:administrativo,contador');
        Route::get('cobros', 'payments');
    });
