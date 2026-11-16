<?php

namespace App\Modules\Caja\Controllers\Api\V1;

use App\Modules\Caja\Services\CajaService;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints stateless de lectura para Caja (cierres y consulta por usuario/fecha).
 */
class CajaApiController extends Controller
{
    public function __construct(private CajaService $service)
    {
    }

    public function cierres(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_usuario' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'tipo_cierre' => ['nullable', 'string', 'in:manual,automatico'],
            'pendiente_conteo' => ['nullable', 'boolean'],
        ]);

        $cierres = $this->service->obtenerHistorialCierres(
            isset($validated['id_usuario']) ? (int) $validated['id_usuario'] : null,
            $validated['desde'] ?? null,
            $validated['hasta'] ?? null,
            $validated['tipo_cierre'] ?? null,
            array_key_exists('pendiente_conteo', $validated) ? (bool) $validated['pendiente_conteo'] : null,
        );

        return response()
            ->json(['data' => array_values($cierres)])
            ->header('Cache-Control', 'no-store');
    }

    public function detalleCierre(int $id): JsonResponse
    {
        $cierre = $this->service->obtenerDetalleCierre($id);

        if ($cierre === null) {
            return response()->json([
                'status' => 'error',
                'message' => "No se encontró el cierre de caja #{$id}.",
            ], 404)->header('Cache-Control', 'no-store');
        }

        return response()
            ->json(['data' => $cierre])
            ->header('Cache-Control', 'no-store');
    }

    public function porUsuarioYFecha(string $fecha, int $idUsuario): JsonResponse
    {
        Validator::validate(['fecha' => $fecha], ['fecha' => ['required', 'date_format:Y-m-d']]);

        $cajas = $this->service->obtenerCajasDeUsuarioYFecha($idUsuario, $fecha);

        return response()
            ->json(['data' => array_values($cajas)])
            ->header('Cache-Control', 'no-store');
    }
}
