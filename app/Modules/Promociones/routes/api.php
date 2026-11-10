<?php

use App\Modules\Login\Middleware\AuthenticateApiSessionRequest;
use App\Modules\Promociones\Controllers\PromocionController;
use Illuminate\Support\Facades\Route;

/*

|--------------------------------------------------------------------------

| API Routes - Módulo Promociones (G4)

|--------------------------------------------------------------------------

| Las carga solo App\Providers\ModuleServiceProvider, con prefijo /api y

| middleware "api". Antes estaban en routes/api.php: se mudaron sin cambiar

| URL ni comportamiento.

| La pantalla /promociones sigue en routes/web.php (la atiende HomeController).

*/

Route::middleware([AuthenticateApiSessionRequest::class, 'api.audience:administrativo,repartidor,contador',

    'api.write.user:administrativo'])->group(function (): void {

        Route::get('/promociones', [PromocionController::class, 'index']);          // Listar todas

        Route::post('/promociones', [PromocionController::class, 'store']);         // Crear nueva

        Route::get('/promociones/{id}', [PromocionController::class, 'show']);      // Ver detalle de una

        Route::put('/promociones/{id}', [PromocionController::class, 'update']);    // Editar una existente

        Route::delete('/promociones/{id}', [PromocionController::class, 'destroy']); // Eliminar una promocion

        Route::patch('/promociones/{id}/estado', [PromocionController::class, 'updateEstado']); // Reactivación / Cambio de estado

    });
