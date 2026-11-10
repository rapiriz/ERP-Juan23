<?php

use App\Http\Controllers\DashboardController;
use App\Modules\Catalogo\Controllers\CatalogoController;
use App\Modules\Clientes\Controllers\ClienteController;
use App\Modules\CuentaCorriente\Controllers\ClientPaymentController;
use App\Modules\Login\Controllers\AuthenticatedSessionController;
use App\Modules\Login\Controllers\PasswordRecoveryController;
use App\Modules\Login\Controllers\UserController;
use App\Modules\Reclamos\Controllers\ReclamoController;
use App\Modules\Reportes\Controllers\OperationalReportController;
use App\Modules\Reportes\Controllers\ReportController;
use App\Modules\Reportes\Controllers\ReportExportController;
use App\Modules\Shared\Controllers\OperationsController;
use App\Modules\Ventas\Controllers\CommercialController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// G1: consulta pública independiente del login y del almacenamiento de sesiones.
Route::prefix('catalogo')->name('catalogo.')->withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
])->group(function (): void {
    Route::get('/', [CatalogoController::class, 'index'])->name('index');
    Route::get('/datos', [CatalogoController::class, 'dataJson'])->name('data');
    Route::get('/{producto}', [CatalogoController::class, 'show'])->where('producto', '[1-9][0-9]{0,9}')->name('show');
});

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1,web-login')
        ->name('login.store');

    Route::get('/recuperar-contrasena', [PasswordRecoveryController::class, 'request'])
        ->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordRecoveryController::class, 'sendCode'])
        ->middleware('throttle:5,1,password-email')
        ->name('password.email');
    Route::get('/recuperar-contrasena/codigo', [PasswordRecoveryController::class, 'code'])
        ->name('password.code');
    Route::post('/recuperar-contrasena/codigo', [PasswordRecoveryController::class, 'verifyCode'])
        ->middleware('throttle:10,1,password-code')
        ->name('password.code.verify');
    Route::get('/recuperar-contrasena/nueva', [PasswordRecoveryController::class, 'reset'])
        ->name('password.reset');
    Route::post('/recuperar-contrasena/nueva', [PasswordRecoveryController::class, 'update'])
        ->middleware('throttle:5,1,password-update')
        ->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'session.current'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::view('/devoluciones', 'devoluciones.index')->middleware('role:administrativo,contador')->name('devoluciones.index');
    Route::get('/devoluciones/{id}/nota', [CommercialController::class, 'notePdf'])->whereNumber('id')->middleware('role:administrativo,contador')->name('devoluciones.nota');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->whereNumber('cliente')->middleware('role:administrativo,repartidor,contador')->name('clientes.show');

    Route::middleware('role:administrativo,repartidor')->group(function (): void {
        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/reclamos', [ReclamoController::class, 'index'])->name('reclamos.index');
        Route::get('/reclamos/nuevo', [ReclamoController::class, 'create'])->name('reclamos.create');
        Route::post('/reclamos', [ReclamoController::class, 'store'])->name('reclamos.store');
    });

    Route::middleware('role:administrativo')->group(function (): void {
        Route::post('/clientes/{cliente}/pagos', [ClientPaymentController::class, 'store'])
            ->whereNumber('cliente')->middleware('throttle:20,1')->name('clientes.payments.store');
        Route::get('/clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->whereNumber('cliente')->name('clientes.edit');
        Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->whereNumber('cliente')->name('clientes.update');
        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
        Route::put('/usuarios/{user}/email', [UserController::class, 'updateEmail'])->name('users.email.update');
    });

    Route::middleware('role:administrativo,contador')->prefix('reportes')->name('reports.')->group(function (): void {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/ventas-diarias', [OperationalReportController::class, 'show'])->defaults('report', 'daily-sales')->name('daily-sales');
        Route::get('/caja-diaria', [OperationalReportController::class, 'show'])->defaults('report', 'daily-cash')->name('daily-cash');
        Route::get('/stock-bajo', [OperationalReportController::class, 'show'])->defaults('report', 'low-stock')->name('low-stock');
        Route::get('/vencimientos', [OperationalReportController::class, 'show'])->defaults('report', 'due-invoices')->name('due-invoices');
        Route::get('/exportar/{report}/{format}', [ReportExportController::class, 'operational'])
            ->where('report', 'daily-sales|daily-cash|low-stock|due-invoices|debtors|product-ranking|client-product-reach')
            ->where('format', 'pdf|xlsx')->name('export');
        Route::get('/historial-clientes', [ReportController::class, 'clientHistory'])->name('client-history');
        Route::get('/clientes-por-producto', [ReportController::class, 'clientProductReach'])->name('client-product-reach');
        Route::get('/clientes-deudores', [ReportController::class, 'debtors'])->name('debtors');
        Route::get('/ranking-comercial', [ReportController::class, 'productRanking'])->name('product-ranking');
        Route::get('/historial-clientes/exportar/{format}', [ReportExportController::class, 'clientHistory'])->name('client-history.export');
    });

    Route::redirect('/stock', '/inventario')
        ->middleware('role:administrativo,repartidor')
        ->name('stock.index');

    Route::view('/promociones', 'promociones.promociones')
        ->middleware('role:administrativo')->name('promociones.index');
});

Route::middleware(['auth', 'session.current', 'role:administrativo'])->group(function () {
    Route::get('/operaciones/stock', [OperationsController::class, 'stock'])->name('operaciones.stock');
    Route::post('/operaciones/stock/{id}/reintentar', [OperationsController::class, 'retry'])->whereNumber('id')->name('operaciones.stock.retry');
    Route::get('/operaciones/historicas', [OperationsController::class, 'historical'])->name('operaciones.historicas');
});
