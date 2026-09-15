<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardReposicionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardReposicionController extends Controller
{
    private DashboardReposicionService $service;

    public function __construct(DashboardReposicionService $service)
    {
        $this->service = $service;
        $this->middleware('auth:sanctum');
    }

    public function resumen(): JsonResponse
    {
        try {
            $resultado = $this->service->obtenerResumenGeneral();
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function graficoMotivos(): JsonResponse
    {
        try {
            $resultado = $this->service->obtenerDatosGraficoMotivos();
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function graficoTendencia(Request $request): JsonResponse
    {
        $dias = $request->input('dias', 30);

        try {
            $resultado = $this->service->obtenerDatosGraficoTendencia((int) $dias);
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function graficoCriticidad(): JsonResponse
    {
        try {
            $resultado = $this->service->obtenerDatosGraficoCriticidad();
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
