<?php

namespace App\Modules\Login\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restringe las escrituras de una API que también ofrece consultas. */
class EnsureApiWriteUser
{
    public function __construct(private EnsureApiUser $users) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        return $request->isMethodSafe()
            ? $next($request)
            : $this->users->handle($request, $next, ...$roles);
    }
}
