<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RedactServerErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! config('app.debug') && $response->getStatusCode() >= 500 && ($response instanceof JsonResponse || $request->expectsJson())) {
            $id = (string) Str::uuid();
            Log::error('Respuesta de error interno protegida', ['request_id' => $id, 'path' => $request->path(), 'status' => $response->getStatusCode()]);

            return response()->json(['message' => 'Server Error'], $response->getStatusCode())->header('Cache-Control', 'no-store')->header('X-Request-ID', $id);
        }

        return $response;
    }
}
