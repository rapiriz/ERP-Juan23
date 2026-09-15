<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ConsultaVencimientosService;
use App\Services\GeneradorSugerenciasReposicionService;
use App\Services\DashboardReposicionService;
use App\Repositories\ProductoLoteRepository;
use App\Repositories\SugerenciaCompraRepository;

class VencimientosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConsultaVencimientosService::class, function ($app) {
            return new ConsultaVencimientosService(
                $app->make(ProductoLoteRepository::class)
            );
        });

        $this->app->singleton(GeneradorSugerenciasReposicionService::class, function ($app) {
            return new GeneradorSugerenciasReposicionService(
                $this->app->make(SugerenciaCompraRepository::class),
                $this->app->make(ProductoLoteRepository::class)
            );
        });

        $this->app->singleton(DashboardReposicionService::class, function ($app) {
            return new DashboardReposicionService(
                $app->make(SugerenciaCompraRepository::class),
                $app->make(ProductoLoteRepository::class)
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
