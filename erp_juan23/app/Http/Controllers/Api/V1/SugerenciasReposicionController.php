<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GeneradorSugerenciasReposicionService;
use App\Repositories\SugerenciaCompraRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SugerenciasReposicionController extends Controller
{
    private GeneradorSugerenciasReposicionService $service;
    private SugerenciaCompraRepository $repository;

    public function __construct(
        GeneradorSugerenciasReposicionService $service,
        SugerenciaCompraRepository $repository
    ) {
        $this->service = $service;
        $this->repository = $repository;
        $this->middleware('auth:sanctum');
    }

    public function generar(): JsonResponse
    {
        try {
            $resultado = $this->service->generarSugerencias();
            return response()->json($resultado, 201);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listar(Request $request): JsonResponse
    {
        $pagina = $request->input('pagina', 1);

        try {
            $resultado = $this->repository->obtenerPendientes($pagina);

            return response()->json([
                'exito' => true,
                'datos' => $resultado['data'],
                'paginacion' => $resultado['paginacion']
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function procesar(int $id): JsonResponse
    {
        try {
            $resultado = $this->service->procesarSugerencia($id);
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function resumen(): JsonResponse
    {
        try {
            $resultado = $this->service->obtenerResumen();
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function rechazar(int $id): JsonResponse
    {
        try {
            $resultado = $this->service->rechazarSugerencia($id);
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function procesarLote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer'
        ]);

        try {
            $resultado = $this->service->procesarMultiples($validated['ids']);
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
