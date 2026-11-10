<?php

namespace App\Providers;

use App\Contracts\SalesGateway;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Clientes\Policies\ClientePolicy;
use App\Modules\Reclamos\Models\Reclamo;
use App\Modules\Reclamos\Policies\ReclamoPolicy;
use App\Modules\Ventas\Contracts\ClienteGateway;
use App\Modules\Ventas\Contracts\ProductoGateway;
use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Integraciones\HttpClienteApi;
use App\Modules\Ventas\Integraciones\HttpProductoApi;
use App\Modules\Ventas\Integraciones\HttpStockApi;
use App\Modules\Ventas\Integraciones\MockStockApi;
use App\Services\Gateways\HttpSalesGateway;
use App\Services\MockClienteApi;
use App\Services\MockProductoApi;
use App\Services\SchemaCaseGuard;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Modules\Facturacion\Contracts\FiscalIssuer::class, \App\Modules\Facturacion\Services\InternalDocumentsOnly::class);
        $this->app->bind(SalesGateway::class, HttpSalesGateway::class);

        // Cada contrato conserva su API real; mock debe elegirse explícitamente.
        foreach ([
            ClienteGateway::class => [HttpClienteApi::class, MockClienteApi::class],
            ProductoGateway::class => [HttpProductoApi::class, MockProductoApi::class],
            StockGateway::class => [HttpStockApi::class, MockStockApi::class],
        ] as $contract => [$real, $mock]) {
            $this->app->scoped($contract, fn ($app) => match (config('ventas.integracion')) {
                'http', 'internal' => $app->make($real),
                'mock' => $app->make($mock),
                default => throw new \InvalidArgumentException('VENTAS_INTEGRACION debe ser internal, http o mock.'),
            });
        }

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MigrationsStarted::class, function () {
            if (app(SchemaCaseGuard::class)->conflicts()) {
                throw new \RuntimeException('Nombres de tablas incompatibles con mayúsculas/minúsculas. Respaldar y revisar el inventario antes de migrar.');
            }
        });
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Reclamo::class, ReclamoPolicy::class);
    }
}
