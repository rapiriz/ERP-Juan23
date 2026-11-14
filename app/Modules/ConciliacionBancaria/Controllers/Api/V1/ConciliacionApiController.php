<?php

namespace App\Modules\ConciliacionBancaria\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\ConciliacionBancaria\Models\Cheque;
use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoints stateless de lectura para Conciliación Bancaria (/api/v1/conciliacion/*).
 */
class ConciliacionApiController extends Controller
{
    public function movimientos(Request $request): JsonResponse
    {
        if (!Schema::hasTable('MOVIMIENTO_BANCARIO')) {
            return $this->tablaNoDisponible('MOVIMIENTO_BANCARIO');
        }

        $validated = $request->validate([
            'estado' => ['nullable', 'string', 'max:40'],
            'tipo_movimiento' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = MovimientoBancario::query();

        if (!empty($validated['estado'])) {
            $query->where('estado_conciliacion', $validated['estado']);
        }
        if (!empty($validated['tipo_movimiento'])) {
            $query->where('tipo_movimiento', $validated['tipo_movimiento']);
        }
        if (!empty($validated['desde'])) {
            $query->whereDate('fecha_operacion', '>=', $validated['desde']);
        }
        if (!empty($validated['hasta'])) {
            $query->whereDate('fecha_operacion', '<=', $validated['hasta']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $movimientos = $query->orderByDesc('fecha_operacion')
            ->orderByDesc('id_movimiento_bancario')
            ->paginate($perPage);

        return response()->json([
            'data' => $movimientos->getCollection()->map(function (MovimientoBancario $mov) {
                return [
                    'id_movimiento_bancario' => (int) $mov->id_movimiento_bancario,
                    'fecha_operacion' => $mov->fecha_operacion?->toDateString(),
                    'monto' => number_format((float) $mov->monto, 2, '.', ''),
                    'signo_monto' => $mov->signo_monto,
                    'descripcion_original' => $mov->descripcion_original,
                    'nro_comprobante_extraido' => $mov->nro_comprobante_extraido,
                    'cuit_extraido' => $mov->cuit_extraido,
                    'tipo_movimiento' => $mov->tipo_movimiento,
                    'estado_conciliacion' => $mov->estado_conciliacion,
                ];
            })->values()->all(),
            'meta' => [
                'current_page' => $movimientos->currentPage(),
                'last_page' => $movimientos->lastPage(),
                'per_page' => $movimientos->perPage(),
                'total' => $movimientos->total(),
            ],
            'links' => [
                'first' => $movimientos->url(1),
                'last' => $movimientos->url($movimientos->lastPage()),
                'prev' => $movimientos->previousPageUrl(),
                'next' => $movimientos->nextPageUrl(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function cheques(Request $request): JsonResponse
    {
        if (!Schema::hasTable('CHEQUE')) {
            return $this->tablaNoDisponible('CHEQUE');
        }

        $validated = $request->validate([
            'estado' => ['nullable', 'string', 'max:40'],
            'id_cobro' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Cheque::query();

        if (!empty($validated['estado'])) {
            $query->where('estado', $validated['estado']);
        }
        if (!empty($validated['id_cobro'])) {
            $query->where('id_cobro', (int) $validated['id_cobro']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $cheques = $query->orderByDesc('id_cheque')->paginate($perPage);

        return response()->json([
            'data' => $cheques->getCollection()->map(function (Cheque $cheque) {
                return [
                    'id_cheque' => (int) $cheque->id_cheque,
                    'id_cobro' => $cheque->id_cobro !== null ? (int) $cheque->id_cobro : null,
                    'numero_cheque' => $cheque->numero_cheque,
                    'banco_emisor' => $cheque->banco_emisor,
                    'fecha_cobro' => $cheque->fecha_cobro?->toDateString(),
                    'estado' => $cheque->estado,
                    'fecha_deposito' => $cheque->fecha_deposito?->toDateString(),
                    'fecha_rechazo' => $cheque->fecha_rechazo?->toDateString(),
                ];
            })->values()->all(),
            'meta' => [
                'current_page' => $cheques->currentPage(),
                'last_page' => $cheques->lastPage(),
                'per_page' => $cheques->perPage(),
                'total' => $cheques->total(),
            ],
            'links' => [
                'first' => $cheques->url(1),
                'last' => $cheques->url($cheques->lastPage()),
                'prev' => $cheques->previousPageUrl(),
                'next' => $cheques->nextPageUrl(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function tablaNoDisponible(string $tabla): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => "El módulo de Conciliación Bancaria no está disponible en esta base (falta tabla {$tabla}).",
        ], 503)->header('Cache-Control', 'no-store');
    }
}
