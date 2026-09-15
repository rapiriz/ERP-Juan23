<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ConsultaVencimientosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultaVencimientosController extends Controller
{
    private ConsultaVencimientosService $service;

    public function __construct(ConsultaVencimientosService $service)
    {
        $this->service = $service;
        $this->middleware('auth:sanctum');
    }

    public function proximosAVencer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dias' => 'integer|min:1|max:365',
            'pagina' => 'integer|min:1',
            'por_pagina' => 'integer|min:1|max:100',
            'producto' => 'nullable|string|max:255',
            'ubicacion' => 'nullable|string|max:255'
        ]);

        $dias = $validated['dias'] ?? 30;
        $pagina = $validated['pagina'] ?? 1;
        $filtros = array_filter([
            'producto' => $validated['producto'] ?? null,
            'ubicacion' => $validated['ubicacion'] ?? null
        ]);

        try {
            $resultado = $this->service->obtenerProximosVencer($dias, $filtros, $pagina);
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function porCriticidad(): JsonResponse
    {
        try {
            $resultado = $this->service->obtenerPorCriticidad();
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function alertas(): JsonResponse
    {
        try {
            $resultado = $this->service->obtenerAlertas();
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reporte(): JsonResponse
    {
        try {
            $resultado = $this->service->generarReporte();
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
