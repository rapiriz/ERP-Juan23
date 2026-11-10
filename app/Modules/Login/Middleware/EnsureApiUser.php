<?php

namespace App\Modules\Login\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUser
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ($roles !== [] && ! in_array($user->rol->value, $roles, true))) {
            return response()->json(['message' => 'No tiene permisos para esta operacion.'], 403);
        }

        return $next($request);
    }
}
