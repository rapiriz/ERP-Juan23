<?php

namespace App\Services\Integrations;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Facade;
use Throwable;

/** Despacha contratos explícitos a las rutas registradas, conservando sus permisos. */
class InternalApiClient
{
    public function __construct(private ?string $serviceToken = null) {}

    private const ACTIONS = [
        'GET' => [
            'App\\Modules\\Clientes\\Controllers\\Api\\V1\\ClientController@index',
            'App\\Modules\\Clientes\\Controllers\\Api\\V1\\ClientController@show',
            'App\\Modules\\Productos\\Controllers\\ProductoController@index',
            'App\\Modules\\Productos\\Controllers\\ProductoController@show',
            'App\\Modules\\Stock\\Controllers\\StockController@show',
            'App\\Modules\\Ventas\\Controllers\\VentaApiController@index',
            'App\\Modules\\Ventas\\Controllers\\VentaApiController@show',
            'App\\Modules\\Rendiciones\\Controllers\\Api\\V1\\RendicionApiController@index',
            'App\\Modules\\Rendiciones\\Controllers\\Api\\V1\\CobroApiController@index',
            'App\\Modules\\CuentaCorriente\\Controllers\\Api\\V1\\FinancialReadController@balance',
            'App\\Modules\\CuentaCorriente\\Controllers\\Api\\V1\\FinancialReadController@debtors',
            'App\\Modules\\CuentaCorriente\\Controllers\\Api\\V1\\FinancialReadController@invoices',
            'App\\Modules\\CuentaCorriente\\Controllers\\Api\\V1\\FinancialReadController@payments',
            'App\\Modules\\Caja\\Controllers\\Api\\V1\\CajaDiariaController',
            'App\\Modules\\Stock\\Controllers\\LoteController@index',
            'App\\Modules\\Proveedores\\Controllers\\SupplierPerformanceController@index',
            'App\\Modules\\Facturacion\\Controllers\\FiscalStatusController',
        ],
        'POST' => ['App\\Modules\\Stock\\Controllers\\StockController@registrarMovimientosLote'],
        'PATCH' => ['App\\Modules\\Ventas\\Controllers\\VentaApiController@cambiarEstado'],
    ];

    public function get(string $path, array $query = [], ?string $token = null): Response
    {
        return $this->request('GET', $path, $query, $token);
    }

    public function post(string $path, array $data = [], ?string $token = null): Response
    {
        return $this->request('POST', $path, $data, $token);
    }

    public function patch(string $path, array $data = [], ?string $token = null): Response
    {
        return $this->request('PATCH', $path, $data, $token);
    }

    public function request(string $method, string $path, array $data = [], ?string $token = null): Response
    {
        abort_unless(preg_match('#^/api/[a-zA-Z0-9/_-]+$#D', $path), 503, 'Ruta de integración interna no permitida.');
        $token ??= $this->serviceToken;
        $app = app();
        $app->make(Kernel::class);
        $router = $app['router'];
        $request = Request::create(rtrim(config('app.url'), '/').$path, $method, $data);
        $request->headers->set('Accept', 'application/json');
        if (is_string($token) && $token !== '') {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        $route = clone $router->getRoutes()->match($request);
        $route->setContainer($app);
        abort_unless(in_array($route->getActionName(), self::ACTIONS[$method] ?? [], true),
            503, 'El contrato no pertenece a una API interna autorizada.');

        // La llamada es de servicio: no transmite cookies ni identidad del visitante.
        // Se omiten solo componentes de cookies/sesión, no autenticación ni permisos.
        $route->withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class]);
        $request->setRouteResolver(fn () => $route);
        $keys = ['request', 'auth', 'auth.driver', 'session', 'session.store'];
        $previous = [];
        foreach ($keys as $key) {
            $previous[$key] = $app->resolved($key) ? $app->make($key) : null;
        }
        $outerResolver = $previous['request']?->getUserResolver();

        try {
            $sessions = new SessionManager($app);
            $sessions->extend(config('session.driver') ?: 'array', fn () => new ArraySessionHandler((int) config('session.lifetime', 120)));
            $app->instance('session', $sessions);
            $app->instance('session.store', $sessions->driver());
            $auth = new AuthManager($app);
            $app->instance('auth', $auth);
            $app->forgetInstance('auth.driver');
            foreach (['auth', 'session', 'request'] as $accessor) {
                Facade::clearResolvedInstance($accessor);
            }
            $app->instance('request', $request);
            $request->setUserResolver(fn ($guard = null) => $auth->guard($guard)->user());

            try {
                $response = (new Pipeline($app))->send($request)
                    ->through($router->gatherRouteMiddleware($route))
                    ->then(fn (Request $request) => $router->prepareResponse($request, $route->run()));
            } catch (Throwable $error) {
                $response = $app->make(ExceptionHandler::class)->render($request, $error);
            }

            return new Response(new PsrResponse($response->getStatusCode(), $response->headers->all(), $response->getContent()));
        } finally {
            foreach (['auth', 'auth.driver', 'session', 'session.store', 'request'] as $key) {
                if ($previous[$key] !== null) {
                    $app->instance($key, $previous[$key]);
                } else {
                    $app->forgetInstance($key);
                }
            }
            if ($previous['request'] && $outerResolver) {
                $previous['request']->setUserResolver($outerResolver);
            }
            foreach (['auth', 'session', 'request'] as $accessor) {
                Facade::clearResolvedInstance($accessor);
            }
        }
    }
}
