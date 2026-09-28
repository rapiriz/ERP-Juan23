<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReclamoController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('login.store');

    Route::get('/recuperar-contrasena', [PasswordRecoveryController::class, 'request'])
        ->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordRecoveryController::class, 'sendCode'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/recuperar-contrasena/codigo', [PasswordRecoveryController::class, 'code'])
        ->name('password.code');
    Route::post('/recuperar-contrasena/codigo', [PasswordRecoveryController::class, 'verifyCode'])
        ->middleware('throttle:10,1')
        ->name('password.code.verify');
    Route::get('/recuperar-contrasena/nueva', [PasswordRecoveryController::class, 'reset'])
        ->name('password.reset');
    Route::post('/recuperar-contrasena/nueva', [PasswordRecoveryController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'session.current'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:administrativo,repartidor')->group(function (): void {
        Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('/reclamos', [ReclamoController::class, 'index'])->name('reclamos.index');
        Route::get('/reclamos/nuevo', [ReclamoController::class, 'create'])->name('reclamos.create');
        Route::post('/reclamos', [ReclamoController::class, 'store'])->name('reclamos.store');
    });

    Route::middleware('role:administrativo')->group(function (): void {
        Route::get('/clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
        Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
        Route::put('/usuarios/{user}/email', [UserController::class, 'updateEmail'])->name('users.email.update');
    });

    Route::view('/ventas', 'modules.ventas')
        ->middleware('role:administrativo,repartidor')
        ->name('ventas.index');
    Route::view('/stock', 'modules.stock')
        ->middleware('role:administrativo,repartidor')
        ->name('stock.index');
});
