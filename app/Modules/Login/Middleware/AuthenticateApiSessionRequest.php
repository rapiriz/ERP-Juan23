<?php

namespace App\Modules\Login\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Autentica APIs con Bearer o sesión vigente y exige CSRF al escribir con sesión. */
class AuthenticateApiSessionRequest implements AuthenticatesRequests
{
    public function __construct(
        private AuthenticateApiRequest $bearer,
        private EnsureCurrentSession $session,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        if ($request->headers->has('Authorization')) {
            $response = $this->bearer->handle($request, $next);
        } else {
            // Las rutas web ya procesaron las cookies y abrieron la sesión.
            $middleware = $request->hasSession() ? [] : [
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
            ];

            $response = app(Pipeline::class)->send($request)->through($middleware)
                ->then(function (Request $request) use ($next): Response {
                    $user = Auth::guard('web')->user();
                    if (! $user) {
                        return response()->json(['message' => 'No autorizado.'], 401);
                    }

                    $request->setUserResolver(fn () => $user);

                    return $this->session->handle($request, function (Request $request) use ($next): Response {
                        return $request->isMethodSafe()
                            ? $next($request)
                            : app(ValidateCsrfToken::class)->handle($request, $next);
                    });
                });
        }

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
