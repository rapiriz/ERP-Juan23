<?php

namespace App\Modules\Productos\Middleware;

use App\Modules\Login\Middleware\AuthenticateApiSessionRequest;
use App\Modules\Login\Middleware\EnsureApiUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El contrato comercial público se amplía sólo para un administrativo autenticado. */
class AuthorizeProductInternals
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->boolean('interno')) {
            return $next($request);
        }

        return app(AuthenticateApiSessionRequest::class)->handle($request,
            fn (Request $request) => app(EnsureApiUser::class)->handle($request, $next, 'administrativo'));
    }
}
