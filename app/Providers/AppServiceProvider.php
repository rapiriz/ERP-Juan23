<?php

namespace App\Providers;

use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Integraciones\HttpClienteApi;
use App\Modules\Ventas\Integraciones\HttpProductoApi;
use App\Modules\Ventas\Integraciones\HttpStockApi;
use App\Modules\Ventas\Integraciones\MockStockApi;
use App\Services\MockClienteApi;
use App\Services\MockProductoApi;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ------------------------------------------------------------------
        // MÓDULO VENTAS: ¿mocks o APIs reales de otros grupos?
        //
        // Cuando una clase pide un ProductoGateway (o Cliente / Stock), Laravel
        // le entrega el mock o la API real según VENTAS_INTEGRACION del .env
        // (config/ventas.php). Por defecto: mock → el front se ve igual que hoy.
        //
        // "scoped" = una sola instancia por pedido HTTP (así las APIs reales
        // pueden recordar lo que ya pidieron y no repetir llamadas).
        // ------------------------------------------------------------------
        $usarApisReales = fn (): bool => config('ventas.integracion') === 'http';

        $this->app->scoped(ProductoGateway::class, fn ($app) => $usarApisReales()
            ? $app->make(HttpProductoApi::class)
            : $app->make(MockProductoApi::class));

        $this->app->scoped(ClienteGateway::class, fn ($app) => $usarApisReales()
            ? $app->make(HttpClienteApi::class)
            : $app->make(MockClienteApi::class));

        $this->app->scoped(StockGateway::class, fn ($app) => $usarApisReales()
            ? $app->make(HttpStockApi::class)
            : $app->make(MockStockApi::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
