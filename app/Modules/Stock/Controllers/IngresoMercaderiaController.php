<?php
declare(strict_types=1);

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Models\Producto;
use App\Modules\Proveedores\Models\Proveedor;
use App\Modules\Stock\Models\Lote;
use App\Modules\Stock\Services\StockService;
use App\Support\UsuarioActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IngresoMercaderiaController extends Controller
{
    public function __construct(private StockService $stockService)
    {
    }

    /**
     * S03 - Registrar ingreso de mercadería de un proveedor.
     * Recibe una lista de productos con cantidades ingresadas y actualiza el stock.
     * Se valida opcionalmente la existencia del proveedor (PV01).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_proveedor' => 'required|integer|exists:PROVEEDOR,id_proveedor',
            'fecha' => 'nullable|date|before_or_equal:today',
            'items' => 'required|array|min:1',
            'items.*.id_producto' => 'required|integer|exists:PRODUCTO,id_producto',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.id_unidad' => 'nullable|integer|exists:UNIDAD_MEDIDA,id_unidad',
            'items.*.motivo' => 'nullable|string|max:255',
            'items.*.nro_lote' => 'nullable|string|max:100',
            'items.*.fecha_vencimiento' => 'nullable|date',
        ], [
            'id_proveedor.required' => 'Debe seleccionar un proveedor.',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe.',
            'fecha.before_or_equal' => 'La fecha del ingreso no puede ser posterior a la fecha actual.',
            'items.required' => 'Debe indicar al menos un producto recibido.',
            'items.*.id_producto.required' => 'Cada ítem debe indicar un producto.',
            'items.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'items.*.cantidad.required' => 'Cada ítem debe indicar la cantidad ingresada.',
            'items.*.cantidad.min' => 'La cantidad ingresada debe ser mayor a cero.',
            'items.*.nro_lote.max' => 'El número de lote no puede superar los 100 caracteres.',
            'items.*.fecha_vencimiento.date' => 'La fecha de vencimiento del lote debe ser una fecha válida.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        foreach ($request->input('items', []) as $index => $item) {
            $nroLote = trim((string) ($item['nro_lote'] ?? ''));
            $fechaVencimiento = trim((string) ($item['fecha_vencimiento'] ?? ''));
            if (($nroLote === '') !== ($fechaVencimiento === '')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Para registrar la trazabilidad, complete juntos el número de lote y su fecha de vencimiento.',
                    'item' => $index,
                ], 422);
            }
        }

        // PV04: impedir nuevas operaciones con proveedores inactivos.
        $proveedor = Proveedor::find((int) $request->input('id_proveedor'));
        if (!$proveedor) {
            return response()->json([
                'status' => 'error',
                'message' => 'El proveedor seleccionado no existe.',
            ], 404);
        }
        if ($proveedor->estado !== 'activo') {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede registrar un ingreso con un proveedor inactivo. (PV04)',
            ], 400);
        }

        $idUsuario = UsuarioActual::id($request);
        $fecha = $request->input('fecha');

        try {
            $items = $request->input('items');
            $productos = [];
            foreach ($items as $item) {
                $producto = Producto::find((int) $item['id_producto']);
                if (!$producto || $producto->estado !== 'activo') {
                    return response()->json([
                        'status' => 'error',
                        'message' => "El producto con ID {$item['id_producto']} no está activo o no existe.",
                    ], 400);
                }
                $productos[(int) $item['id_producto']] = $producto;
            }

            $registrados = DB::transaction(function () use ($items, $productos, $idUsuario, $fecha) {
                $registrados = [];
                foreach ($items as $item) {
                $producto = $productos[(int) $item['id_producto']];
                $idUnidad = isset($item['id_unidad']) ? (int) $item['id_unidad'] : null;
                $motivo = $item['motivo'] ?? 'Ingreso de mercadería (S03)';
                $cantidad = (int) $item['cantidad'];

                // S09: si el item trae lote, el movimiento y el LOTE se registran
                // dentro de la transacción completa del ingreso.
                $nroLote = isset($item['nro_lote']) ? trim((string) $item['nro_lote']) : null;
                $vence = $item['fecha_vencimiento'] ?? null;

                $resultado = (function () use (
                    $producto,
                    $cantidad,
                    $motivo,
                    $idUsuario,
                    $idUnidad,
                    $nroLote,
                    $vence
                ) {
                    $movimiento = $this->stockService->registrarMovimiento(
                        $producto,
                        'ingreso',
                        $cantidad,
                        $motivo,
                        $idUsuario,
                        $idUnidad
                    );

                    $loteId = null;
                    if ($nroLote !== null && $nroLote !== '' && $vence !== null) {
                        $lote = Lote::where('id_producto', $producto->id_producto)
                            ->where('nro_lote', $nroLote)
                            ->lockForUpdate()
                            ->first();

                        if ($lote && $lote->fecha_vencimiento->toDateString() !== \Carbon\Carbon::parse($vence)->toDateString()) {
                            throw new \RuntimeException(
                                "El lote '{$nroLote}' ya está registrado para el producto {$producto->codigo} con otra fecha de vencimiento."
                            );
                        }

                        $cantidadBase = StockService::cantidadEnUnidadBase($cantidad, $idUnidad);
                        if ($lote) {
                            $lote->cantidad_inicial = (int) $lote->cantidad_inicial + $cantidadBase;
                            $lote->cantidad_actual = (int) $lote->cantidad_actual + $cantidadBase;
                            $lote->save();
                        } else {
                            $lote = Lote::create([
                                'id_producto' => $producto->id_producto,
                                'nro_lote' => $nroLote,
                                'cantidad_inicial' => $cantidadBase,
                                'cantidad_actual' => $cantidadBase,
                                'fecha_vencimiento' => $vence,
                            ]);
                        }

                        $movimiento->update(['id_lote' => $lote->id_lote]);
                        $loteId = $lote->id_lote;
                    }

                    return $loteId;
                })();

                $producto->refresh();

                $registrados[] = [
                    'id_producto' => $producto->id_producto,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->descripcion,
                    'cantidad' => $cantidad,
                    'id_unidad' => $idUnidad,
                    'id_lote' => $resultado,
                    'nro_lote' => $nroLote,
                    'fecha_vencimiento' => $vence,
                    'stock_disponible' => $producto->stock_disponible,
                    'estado_alerta' => $producto->estado_alerta,
                ];
            }
                return $registrados;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Ingreso de mercadería registrado exitosamente.',
                'data' => [
                    'id_proveedor' => (int) $request->input('id_proveedor'),
                    'fecha' => $fecha,
                    'items' => $registrados,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar el ingreso de mercadería: ' . $e->getMessage(),
            ], 500);
        }
    }
}
