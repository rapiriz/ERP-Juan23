<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\UsuarioActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Se ejecuta después de autenticar y autorizar la solicitud. */
class IdentidadUsuario
{
    public function handle(Request $request, Closure $next): Response
    {

        $request->attributes->set(UsuarioActual::ATTRIBUTO, UsuarioActual::id($request));

        return $next($request);

    }

}
