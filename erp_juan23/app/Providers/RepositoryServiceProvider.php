<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\ProductoLoteRepository;
use App\Repositories\SugerenciaCompraRepository;
use App\Models\ProductoLote;
use App\Models\SugerenciaCompra;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductoLoteRepository::class, function ($app) {
            return new ProductoLoteRepository($app->make(ProductoLote::class));
        });

        $this->app->singleton(SugerenciaCompraRepository::class, function ($app) {
            return new SugerenciaCompraRepository($app->make(SugerenciaCompra::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
