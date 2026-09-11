<?php
declare(strict_types=1);

namespace App\Modules\Compras\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compras\Models\Compra;
use App\Modules\Compras\Models\PagoCompra;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** C08 - Registrar uno o varios medios de pago para una compra. */
class PagoCompraController extends Controller
{
    public const METODOS = ['efectivo', 'transferencia', 'tarjeta_debito', 'tarjeta_credito', 'cheque', 'otro'];

    public function store(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha_pago' => 'nullable|date|before_or_equal:today',
            'id_usuario' => 'nullable|integer',
            'pagos' => 'required|array|min:1',
            'pagos.*.metodo' => 'required|string|in:' . implode(',', self::METODOS),
            'pagos.*.importe' => 'required|numeric|gt:0',
            'pagos.*.fecha_pago' => 'nullable|date|before_or_equal:today',
            'pagos.*.referencia' => 'nullable|string|max:100',
        ], [
            'pagos.required' => 'Debe indicar al menos un medio de pago.',
            'pagos.min' => 'Debe indicar al menos un medio de pago.',
            'pagos.*.metodo.in' => 'El medio de pago indicado no es válido.',
            'pagos.*.importe.gt' => 'Cada importe debe ser mayor a cero.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser posterior a la fecha actual.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 400);
        }

        try {
            $resultado = DB::transaction(function () use ($request, $id) {
                $compra = Compra::lockForUpdate()->find($id);
                if (!$compra) {
                    abort(404, 'La compra no existe.');
                }
                if ($compra->estado === 'cancelada') {
                    abort(400, 'No se pueden registrar pagos para una compra cancelada.');
                }

                $totalPagado = (float) PagoCompra::where('id_compra', $compra->id_compra)->sum('importe');
                $saldo = round((float) $compra->importe_total - $totalPagado, 2);
                $nuevosPagos = $request->input('pagos');
                $importeNuevo = round(array_sum(array_map(fn (array $p) => (float) $p['importe'], $nuevosPagos)), 2);

                if ($importeNuevo > $saldo + 0.00001) {
                    abort(400, 'El importe de los pagos supera el saldo pendiente de $' . number_format($saldo, 2, '.', '') . '.');
                }

                $fechaPredeterminada = $request->input('fecha_pago') ?? Carbon::now()->toDateString();
                $idUsuario = (int) $request->input('id_usuario', 1);
                $pagos = [];

                foreach ($nuevosPagos as $pago) {
                    $pagoCreado = PagoCompra::create([
                        'id_compra' => $compra->id_compra,
                        'metodo' => $pago['metodo'],
                        'importe' => round((float) $pago['importe'], 2),
                        'fecha_pago' => $pago['fecha_pago'] ?? $fechaPredeterminada,
                        'referencia' => $pago['referencia'] ?? null,
                        'id_usuario' => $idUsuario,
                    ]);
                    $pagos[] = $pagoCreado;
                }

                $compra->saldo_pendiente = round($saldo - $importeNuevo, 2);
                $compra->save();

                return compact('compra', 'pagos');
            });

            return response()->json([
                'status' => 'success',
                'message' => count($resultado['pagos']) > 1
                    ? 'Pagos registrados exitosamente.'
                    : 'Pago registrado exitosamente.',
                'data' => [
                    'id_compra' => $resultado['compra']->id_compra,
                    'importe_total' => (float) $resultado['compra']->importe_total,
                    'saldo_pendiente' => (float) $resultado['compra']->saldo_pendiente,
                    'estado_pago' => (float) $resultado['compra']->saldo_pendiente <= 0 ? 'pagada' : 'pendiente',
                    'pagos' => array_map(fn (PagoCompra $p) => $this->formato($p), $resultado['pagos']),
                ],
            ], 201);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Error al registrar los pagos: ' . $e->getMessage()], 500);
        }
    }

    private function formato(PagoCompra $pago): array
    {
        return [
            'id_pago_compra' => $pago->id_pago_compra,
            'metodo' => $pago->metodo,
            'importe' => (float) $pago->importe,
            'fecha_pago' => $pago->fecha_pago?->format('Y-m-d'),
            'referencia' => $pago->referencia,
        ];
    }
}
