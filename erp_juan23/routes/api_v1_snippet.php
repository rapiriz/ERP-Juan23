<?php

// ⚠️ Este archivo es un SNIPPET para fusionar dentro de tu routes/api.php existente,
// no lo cargues tal cual (Laravel no auto-carga routes/api_v1_snippet.php).
// Copia el contenido del Route::middleware(...) dentro de tu routes/api.php.

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // Consulta de vencimientos (S11)
    Route::prefix('vencimientos')->group(function () {
        Route::get('/proximos', [\App\Http\Controllers\Api\V1\ConsultaVencimientosController::class, 'proximosAVencer']);
        Route::get('/por-criticidad', [\App\Http\Controllers\Api\V1\ConsultaVencimientosController::class, 'porCriticidad']);
        Route::get('/alertas', [\App\Http\Controllers\Api\V1\ConsultaVencimientosController::class, 'alertas']);
        Route::get('/reporte', [\App\Http\Controllers\Api\V1\ConsultaVencimientosController::class, 'reporte']);
    });

    // Sugerencias de reposición (PC07)
    Route::prefix('sugerencias-reposicion')->group(function () {
        Route::post('/generar', [\App\Http\Controllers\Api\V1\SugerenciasReposicionController::class, 'generar']);
        Route::get('/', [\App\Http\Controllers\Api\V1\SugerenciasReposicionController::class, 'listar']);
        Route::post('/procesar-lote', [\App\Http\Controllers\Api\V1\SugerenciasReposicionController::class, 'procesarLote']);
        Route::post('/{id}/procesar', [\App\Http\Controllers\Api\V1\SugerenciasReposicionController::class, 'procesar']);
        Route::post('/{id}/rechazar', [\App\Http\Controllers\Api\V1\SugerenciasReposicionController::class, 'rechazar']);
        Route::get('/resumen', [\App\Http\Controllers\Api\V1\SugerenciasReposicionController::class, 'resumen']);
    });

    // Dashboard de reposición (gráficos)
    Route::prefix('dashboard-reposicion')->group(function () {
        Route::get('/resumen', [\App\Http\Controllers\Api\V1\DashboardReposicionController::class, 'resumen']);
        Route::get('/grafico-motivos', [\App\Http\Controllers\Api\V1\DashboardReposicionController::class, 'graficoMotivos']);
        Route::get('/grafico-tendencia', [\App\Http\Controllers\Api\V1\DashboardReposicionController::class, 'graficoTendencia']);
        Route::get('/grafico-criticidad', [\App\Http\Controllers\Api\V1\DashboardReposicionController::class, 'graficoCriticidad']);
    });
});
