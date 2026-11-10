<?php

declare(strict_types=1);

namespace App\Modules\Compras\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compras\Models\PagoProveedor;
use App\Modules\Compras\Services\PagoCompraService;
use App\Support\UsuarioActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * C08 - Registrar múltiples métodos de pago de una compra.
 *
 * Los pagos son 1..N contra la compra: se pueden cargar tantos como haga falta y
 * con métodos distintos en cada registro.
 */
class PagoCompraController extends Controller
{
    public function destroy(Request $request, int $compra, int $pago): JsonResponse
    {
        $request->validate(['motivo' => 'sometimes|required|string|min:3|max:1000']);
        $result = $this->service->anular($compra, $pago, UsuarioActual::id($request),
            $request->input('motivo', 'Anulación administrativa de pago'));
        return response()->json(['status' => 'success', 'message' => 'Pago anulado; el original y su auditoría se conservan.', 'data' => $result]);
    }
    public function __construct(private PagoCompraService $service) {}

    /**
     * Registrar un pago de la compra.
     */
    public function store(Request $request, int $compra): JsonResponse
    {
        $multiple = $request->has('pagos');
        if ($multiple && ($request->has('importe') || $request->has('monto') || $request->has('metodo_pago'))) {
            return response()->json(['status' => 'error', 'message' => 'Enviá pagos o un pago individual, sin mezclar ambos formatos.'], 400);
        }
        if ($multiple && is_array($request->input('pagos'))) {
            $request->merge(['pagos' => array_map(fn ($p) => is_array($p) ? [
                'metodo_pago' => $p['metodo_pago'] ?? null,
                'importe' => $p['importe'] ?? $p['monto'] ?? null,
                'fecha_pago' => $p['fecha_pago'] ?? $p['fecha'] ?? now()->toDateString(),
            ] : $p, $request->input('pagos'))]);
        } elseif (! $multiple && ! $request->has('importe') && $request->has('monto')) {
            $request->merge(['importe' => $request->input('monto')]);
        }

        $singleRules = [
            'metodo_pago' => 'required|string|in:'.implode(',', PagoProveedor::METODOS),
            'importe' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'fecha_pago' => 'nullable|date|before_or_equal:today',
        ];
        $rules = $singleRules;
        if ($multiple) {
            $rules = ['pagos' => 'required|array|min:1|max:100', 'pagos.*' => 'required|array'];
            foreach ($singleRules as $field => $rule) {
                $rules['pagos.*.'.$field] = $rule;
            }
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        try {
            $valid = $validator->validated();
            $resultado = $multiple
                ? $this->service->registrarMultiples($compra, $valid['pagos'], UsuarioActual::id($request))
                : $this->service->registrar($compra, [
                    'metodo_pago' => $valid['metodo_pago'], 'importe' => $valid['importe'],
                    'fecha_pago' => $valid['fecha_pago'] ?? now()->toDateString(),
                ], UsuarioActual::id($request));

            return response()->json([
                'status' => 'success',
                'message' => 'Pago registrado exitosamente.',
                'data' => $resultado,
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar el pago: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar los pagos de la compra con el desglose por método.
     */
    public function index(int $compra): JsonResponse
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->service->resumen($compra),
            ], 200);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar los pagos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resumen de la deuda de la compra (importe total, pagado y saldo a pagar).
     */
    public function resumen(int $compra): JsonResponse
    {
        try {
            $r = $this->service->resumen($compra);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'importe_total' => $r['importe_total'],
                    'total_pagado' => $r['total_pagado'],
                    'saldo_a_pagar' => $r['saldo_a_pagar'],
                    'cantidad_pagos' => $r['cantidad_pagos'],
                    'pagos_completos' => $r['pagos_completos'],
                    'por_metodo' => $r['por_metodo'],
                ],
            ], 200);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar el resumen de pagos: '.$e->getMessage(),
            ], 500);
        }
    }
}
