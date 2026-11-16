<?php

use App\Modules\Caja\Controllers\Api\V1\CajaApiController;
use App\Modules\Caja\Controllers\Api\V1\CajaDiariaController;
use App\Modules\Catalogo\Controllers\CatalogoApiController;
use App\Modules\Clientes\Controllers\Api\V1\ClientController;
use App\Modules\Clientes\Controllers\Api\V1\ZoneController;
use App\Modules\ConciliacionBancaria\Controllers\Api\V1\ConciliacionApiController;
use App\Modules\Entregas\Controllers\Api\V1\EntregaApiController;
use App\Modules\Login\Controllers\Api\V1\AuthController;
use App\Modules\Login\Controllers\Api\V1\UserController;
use App\Modules\Login\Middleware\AuthenticateApiReadRequest;
use App\Modules\Reclamos\Controllers\Api\V1\ClaimController;
use App\Modules\Rendiciones\Controllers\Api\V1\CobroApiController;
use App\Modules\Rendiciones\Controllers\Api\V1\RendicionApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/catalogo', [CatalogoApiController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/catalogo/{producto}', [CatalogoApiController::class, 'show'])->where('producto', '[1-9][0-9]{0,9}')->middleware('throttle:60,1');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware([AuthenticateApiReadRequest::class, 'throttle:60,1'])->group(function (): void {
        Route::get('/health', fn () => response()->json(['status' => 'ok'])
            ->header('Cache-Control', 'no-store'));
        Route::get('/clientes', [ClientController::class, 'index'])->middleware('api.audience:administrativo,repartidor');
        Route::get('/clientes/{cliente}', [ClientController::class, 'show'])->whereNumber('cliente')->middleware('api.audience:administrativo,repartidor');
        Route::get('/zonas', ZoneController::class)->middleware('api.audience:administrativo,repartidor');
        Route::get('/reclamos', [ClaimController::class, 'index'])->middleware('api.audience:administrativo,repartidor');
        Route::get('/reclamos/{reclamo}', [ClaimController::class, 'show'])->whereNumber('reclamo')->middleware('api.audience:administrativo,repartidor');
        Route::get('/usuarios/{user}', UserController::class)->whereNumber('user')->middleware('api.audience:administrativo');

        // APIs stateless de G2 (Caja, Rendiciones, Cobros, Entregas/Remitos, Conciliación).
        Route::get('/caja/cierres', [CajaApiController::class, 'cierres'])
            ->middleware('api.audience:administrativo,contador');
        Route::get('/caja/cierres/{id}', [CajaApiController::class, 'detalleCierre'])
            ->whereNumber('id')
            ->middleware('api.audience:administrativo,contador');
        Route::get('/caja/{fecha}/{id_usuario}', [CajaApiController::class, 'porUsuarioYFecha'])
            ->where('fecha', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
            ->whereNumber('id_usuario')
            ->middleware('api.audience:administrativo,contador');
        Route::get('/caja/{fecha}', CajaDiariaController::class)
            ->where('fecha', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
            ->middleware('api.audience:administrativo,contador');

        Route::get('/rendiciones', [RendicionApiController::class, 'index'])
            ->middleware('api.audience:administrativo,repartidor,contador');
        Route::get('/rendiciones/{id}', [RendicionApiController::class, 'show'])
            ->whereNumber('id')
            ->middleware('api.audience:administrativo,repartidor,contador');
        Route::get('/cobros', [CobroApiController::class, 'index'])
            ->middleware('api.audience:administrativo,repartidor,contador');

        Route::get('/entregas', [EntregaApiController::class, 'index'])
            ->middleware('api.audience:administrativo,repartidor,contador');
        Route::get('/entregas/{id}', [EntregaApiController::class, 'show'])
            ->whereNumber('id')
            ->middleware('api.audience:administrativo,repartidor,contador');
        Route::get('/remitos/{id}', [EntregaApiController::class, 'showRemito'])
            ->whereNumber('id')
            ->middleware('api.audience:administrativo,repartidor,contador');

        Route::get('/conciliacion/movimientos', [ConciliacionApiController::class, 'movimientos'])
            ->middleware('api.audience:administrativo,contador');
        Route::get('/conciliacion/cheques', [ConciliacionApiController::class, 'cheques'])
            ->middleware('api.audience:administrativo,contador');
    });

    Route::middleware(['api.access', 'throttle:60,1'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('api.user');

        Route::post('/clientes', [ClientController::class, 'store'])->middleware('api.user:administrativo');
        Route::put('/clientes/{cliente}', [ClientController::class, 'replace'])->whereNumber('cliente')->middleware('api.user:administrativo');
        Route::patch('/clientes/{cliente}', [ClientController::class, 'patch'])->whereNumber('cliente')->middleware('api.user:administrativo');
        Route::get('/clientes/{cliente}/saldo', [ClientController::class, 'balance'])->whereNumber('cliente')->middleware('api.audience:administrativo,repartidor');
        Route::get('/clientes/{cliente}/cobros', [ClientController::class, 'payments'])->whereNumber('cliente')->middleware('api.audience:administrativo,repartidor');
        Route::post('/reclamos', [ClaimController::class, 'store'])->middleware('api.audience:administrativo,repartidor');
        Route::patch('/reclamos/{reclamo}/estado', [ClaimController::class, 'updateStatus'])->whereNumber('reclamo')->middleware('api.audience:administrativo,repartidor');

    });
});
