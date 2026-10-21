<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
\Illuminate\Support\Facades\Artisan::command('caja:cerrar-vencidas', function () {
    $cantidad = app(\App\Caja\Services\CajaService::class)->cerrarCajasVencidas();
    $this->info("Cajas cerradas automaticamente: {$cantidad}");
})->purpose('Cierra las cajas de repartidor vencidas (72 h), sin contar');

\Illuminate\Support\Facades\Schedule::command('caja:cerrar-vencidas')->everyFifteenMinutes()->withoutOverlapping();
