<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\Reclamo;
use App\Policies\ClientePolicy;
use App\Policies\ReclamoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Reclamo::class, ReclamoPolicy::class);
    }
}
