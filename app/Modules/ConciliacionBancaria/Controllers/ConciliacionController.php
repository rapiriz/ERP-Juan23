<?php

namespace App\Modules\ConciliacionBancaria\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ConciliacionBancaria\Services\ConciliacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class ConciliacionController extends Controller
{
    private ConciliacionService $service;

    public function __construct(ConciliacionService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/conciliacion/propuestas
     * Retorna sugerencias de auto-matching (alta confianza y para revisar).
     */
    public function propuestas(): JsonResponse
    {
        $sugerencias = $this->service->obtenerSugerencias();
        return response()->json([
            'success' => true,
            'data' => $sugerencias,
        ]);
    }

    /**
     * POST /api/conciliacion/auto-matching
     * Ejecuta el cruce automático de movimientos de alta confianza.
     */
    public function autoMatching(): JsonResponse
    {
        $resultado = $this->service->ejecutarAutoMatching();
        return response()->json([
            'success' => true,
            'mensaje' => "Auto-matching completado: {$resultado['total_procesados']} movimientos conciliados.",
            'data' => $resultado,
        ]);
    }

    /**
     * POST /api/conciliacion/manual
     * Conciliación manual entre 1 movimiento bancario y 1 cobro de rendición.
     */
    public function conciliarManual(Request $request): JsonResponse
    {
        $request->validate([
            'id_movimiento_bancario' => ['required', 'integer'],
            'id_rendicion_cobro' => ['required', 'integer'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $mov = $this->service->conciliarManual(
                (int)$request->input('id_movimiento_bancario'),
                (int)$request->input('id_rendicion_cobro'),
                $request->input('observaciones')
            );

            return response()->json([
                'success' => true,
                'mensaje' => 'Conciliación manual confirmada exitosamente.',
                'data' => $mov,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'codigo' => 422,
                'mensaje' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * PATCH /api/conciliacion/movimientos/{id}/estado
     * HU-03: Gestionar excepciones (en_disputa, revision_manual, no_aplica, cerrado_definitivo).
     */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado' => ['required', 'string', 'in:en_disputa,revision_manual,no_aplica,cerrado_definitivo,pendiente'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $mov = $this->service->gestionarExcepcion(
                $id,
                $request->input('estado'),
                $request->input('motivo')
            );

            return response()->json([
                'success' => true,
                'mensaje' => "Estado del movimiento actualizado a '{$mov->estado_conciliacion}'.",
                'data' => $mov,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'codigo' => 422,
                'mensaje' => $e->getMessage(),
            ], 422);
        }
    }
}
