<?php
declare(strict_types=1);

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Models\Lote;
use App\Modules\Stock\Services\StockService;
use App\Support\UsuarioActual;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LoteController extends Controller
{
    public function __construct(private StockService $stockService)
    {
    }

    /**
     * Listar lotes registrados con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $idProducto = $request->filled('id_producto') ? (int) $request->input('id_producto') : null;
        $estado = $request->input('estado');
        if ($estado === 'todos') {
            $estado = null;
        }
        $search = $request->input('search');
        $porVencer = filter_var($request->input('por_vencer', false), FILTER_VALIDATE_BOOLEAN);
        $dias = (int) $request->input('dias', 30);

        $query = Lote::with(['producto', 'unidad', 'movimiento'])
            ->orderBy('fecha_vencimiento', 'asc')
            ->orderBy('id_lote', 'desc');

        if ($idProducto) {
            $query->where('id_producto', $idProducto);
        }

        if ($porVencer) {
            $query->porVencer($dias);
        } elseif ($estado) {
            $query->where('estado', strtolower($estado));
        }

        if ($search) {
            $query->search($search);
        }

        $lotes = $query->get()->map(function (Lote $l) {
            return $this->formatearLote($l);
        });

        return response()->json([
            'status' => 'success',
            'data' => $lotes,
        ]);
    }

    /**
     * Registrar un nuevo lote por producto y asociarlo opcionalmente a un ingreso de stock.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_producto' => 'required|integer|exists:PRODUCTO,id_producto',
            'nro_lote' => 'required|string|max:100',
            'fecha_vencimiento' => 'required|date',
            'cantidad' => 'required|integer|min:1',
            'id_unidad' => 'nullable|integer|exists:UNIDAD_MEDIDA,id_unidad',
            'asociar_ingreso' => 'nullable|boolean',
            'motivo' => 'nullable|string|max:255',
        ], [
            'id_producto.required' => 'Debe seleccionar un producto.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'nro_lote.required' => 'El número de lote es obligatorio.',
            'nro_lote.max' => 'El número de lote no puede superar los 100 caracteres.',
            'fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
            'fecha_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'cantidad.required' => 'Debe indicar la cantidad del lote.',
            'cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'id_unidad.exists' => 'La unidad de medida seleccionada no existe.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        /** @var Producto $producto */
        $producto = Producto::find((int) $request->input('id_producto'));
        if (!$producto || $producto->estado !== 'activo') {
            return response()->json([
                'status' => 'error',
                'message' => 'El producto seleccionado no se encuentra activo o no existe.',
            ], 400);
        }

        $idUsuario = UsuarioActual::id($request);
        $asociarIngreso = filter_var($request->input('asociar_ingreso', true), FILTER_VALIDATE_BOOLEAN);
        $cantidad = (int) $request->input('cantidad');
        $idUnidad = $request->filled('id_unidad') ? (int) $request->input('id_unidad') : null;
        $nroLote = trim((string) $request->input('nro_lote'));
        $fechaVencimiento = Carbon::parse($request->input('fecha_vencimiento'))->toDateString();
        $motivo = $request->input('motivo') ?? "Ingreso mercadería lote {$nroLote}";

        // Determinar estado inicial según la fecha de vencimiento
        $estado = Carbon::parse($fechaVencimiento)->isPast() ? 'vencido' : 'vigente';

        try {
            $lote = DB::transaction(function () use (
                $producto,
                $nroLote,
                $fechaVencimiento,
                $cantidad,
                $idUnidad,
                $estado,
                $asociarIngreso,
                $motivo,
                $idUsuario
            ) {
                $idMovimiento = null;

                if ($asociarIngreso) {
                    $movimiento = $this->stockService->registrarMovimiento(
                        $producto,
                        'ingreso',
                        $cantidad,
                        $motivo,
                        $idUsuario,
                        $idUnidad
                    );
                    $idMovimiento = $movimiento->id_movimiento;
                }

                return Lote::create([
                    'id_producto' => $producto->id_producto,
                    'id_movimiento' => $idMovimiento,
                    'id_unidad' => $idUnidad,
                    'nro_lote' => $nroLote,
                    'cantidad' => $cantidad,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => $estado,
                ]);
            });

            $lote->load(['producto', 'unidad', 'movimiento']);
            $producto->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Lote registrado exitosamente.',
                'data' => [
                    'lote' => $this->formatearLote($lote),
                    'stock_disponible' => $producto->stock_disponible,
                    'estado_alerta' => $producto->estado_alerta,
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar el lote: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar detalle de un lote específico.
     */
    public function show(int $id): JsonResponse
    {
        $lote = Lote::with(['producto', 'unidad', 'movimiento'])->find($id);
        if (!$lote) {
            return response()->json([
                'status' => 'error',
                'message' => 'El lote solicitado no existe.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatearLote($lote),
        ]);
    }

    /**
     * Consultar historial de lotes de un producto específico.
     */
    public function lotesPorProducto(int $idProducto): JsonResponse
    {
        $producto = Producto::find($idProducto);
        if (!$producto) {
            return response()->json([
                'status' => 'error',
                'message' => 'El producto solicitado no existe.',
            ], 404);
        }

        $lotes = Lote::with(['unidad', 'movimiento'])
            ->porProducto($idProducto)
            ->orderBy('fecha_vencimiento', 'asc')
            ->orderBy('id_lote', 'desc')
            ->get()
            ->map(function (Lote $l) use ($producto) {
                $item = $this->formatearLote($l);
                $item['codigo_producto'] = $producto->codigo;
                $item['descripcion_producto'] = $producto->descripcion;
                return $item;
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'id_producto' => $producto->id_producto,
                'codigo' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'stock_disponible' => $producto->stock_disponible,
                'total_lotes' => $lotes->count(),
                'lotes' => $lotes,
            ],
        ]);
    }

    /**
     * Endpoint especializado para lotes próximos a vencer (dashboard y alertas).
     */
    public function porVencer(Request $request): JsonResponse
    {
        $dias = (int) $request->input('dias', 30);
        if ($dias < 1) {
            $dias = 30;
        }

        $lotes = Lote::with(['producto', 'unidad'])
            ->porVencer($dias)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get()
            ->map(function (Lote $l) {
                return $this->formatearLote($l);
            });

        return response()->json([
            'status' => 'success',
            'data' => $lotes,
        ]);
    }

    /**
     * Normalizar estructura de respuesta de un lote.
     */
    private function formatearLote(Lote $l): array
    {
        $dias = $l->dias_para_vencer;
        $urgente = ($dias !== null && $dias <= 15 && $dias >= 0);
        $vencido = ($dias !== null && $dias < 0);

        return [
            'id_lote' => $l->id_lote,
            'nro_lote' => $l->nro_lote,
            'id_producto' => $l->id_producto,
            'codigo_producto' => $l->producto ? $l->producto->codigo : null,
            'descripcion_producto' => $l->producto ? $l->producto->descripcion : null,
            'cantidad' => $l->cantidad,
            'id_unidad' => $l->id_unidad,
            'nombre_unidad' => $l->unidad ? $l->unidad->nombre_unidad : null,
            'fecha_vencimiento' => $l->fecha_vencimiento ? $l->fecha_vencimiento->format('Y-m-d') : null,
            'dias_para_vencer' => $dias,
            'estado' => $vencido ? 'vencido' : (string) $l->estado,
            'urgente' => $urgente,
            'id_movimiento' => $l->id_movimiento,
            'fecha_ingreso' => $l->movimiento && $l->movimiento->fecha ? $l->movimiento->fecha->format('Y-m-d') : null,
        ];
    }
}
