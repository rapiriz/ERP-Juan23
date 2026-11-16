<?php

namespace App\Modules\ConciliacionBancaria\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\ConciliacionBancaria\Services\BankStatementImportService;
use App\Support\UsuarioActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class MovimientoBancarioController extends Controller
{
    private BankStatementImportService $importService;

    public function __construct(BankStatementImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * GET /api/movimientos-bancarios
     * Lista movimientos bancarios con filtros múltiples por fecha, estado, tipo,
     * rango numérico con signo, toggle de signo, CUIT, comprobante y descripción.
     */
    public function index(Request $request): JsonResponse
    {
        // Cargamos la relación directamente para evitar consultas N+1
        $query = MovimientoBancario::query()->with('rendicionCobro');

        // 1. Rango de fechas de operación
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_operacion', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_operacion', '<=', $request->input('fecha_hasta'));
        }

        // 2. Estado de conciliación (si llega '__ninguno__' o vacío explícito, devuelve 0 filas)
        if ($request->has('estado_conciliacion')) {
            $estadosRaw = $request->input('estado_conciliacion');
            if ($estadosRaw === '__ninguno__' || $estadosRaw === '') {
                $query->whereRaw('1 = 0');
            } else {
                $estados = is_array($estadosRaw)
                    ? $estadosRaw
                    : array_filter(array_map('trim', explode(',', (string) $estadosRaw)));
                if (!empty($estados)) {
                    $query->whereIn('estado_conciliacion', $estados);
                }
            }
        }

        // 3. Tipo de movimiento (si llega '__ninguno__' o vacío explícito, devuelve 0 filas)
        if ($request->has('tipo_movimiento')) {
            $tiposRaw = $request->input('tipo_movimiento');
            if ($tiposRaw === '__ninguno__' || $tiposRaw === '') {
                $query->whereRaw('1 = 0');
            } else {
                $tipos = is_array($tiposRaw)
                    ? $tiposRaw
                    : array_filter(array_map('trim', explode(',', (string) $tiposRaw)));
                if (!empty($tipos)) {
                    $query->whereIn('tipo_movimiento', $tipos);
                }
            }
        }

        // 4. Rango de importes evaluado sobre el valor real con signo (+ ingreso / - egreso)
        $exprMontoConSigno = "CASE WHEN signo_monto = 'egreso' THEN -monto ELSE monto END";

        if ($request->filled('monto_min') && is_numeric($request->input('monto_min'))) {
            $query->whereRaw("{$exprMontoConSigno} >= ?", [(float) $request->input('monto_min')]);
        }
        if ($request->filled('monto_max') && is_numeric($request->input('monto_max'))) {
            $query->whereRaw("{$exprMontoConSigno} <= ?", [(float) $request->input('monto_max')]);
        }

        // 5. Signo del movimiento ('ingreso' o 'egreso')
        if ($request->filled('signo') && in_array($request->input('signo'), ['ingreso', 'egreso'], true)) {
            $query->where('signo_monto', $request->input('signo'));
        }

        // 6. Filtro específico por CUIT (solo dígitos, busca en columna extraída y en descripción)
        if ($request->filled('cuit')) {
            $cuitLimpio = preg_replace('/\D/', '', (string) $request->input('cuit'));
            if ($cuitLimpio !== '') {
                $query->where(function ($sub) use ($cuitLimpio) {
                    $sub->where('cuit_extraido', 'like', "%{$cuitLimpio}%")
                        ->orWhere('descripcion_normalizada', 'like', "%{$cuitLimpio}%");
                });
            }
        }

        // 7. Filtro específico por N° de Comprobante / Operación
        if ($request->filled('comprobante')) {
            $comp = trim((string) $request->input('comprobante'));
            if ($comp !== '') {
                $query->where(function ($sub) use ($comp) {
                    $sub->where('nro_comprobante_extraido', 'like', "%{$comp}%")
                        ->orWhere('descripcion_normalizada', 'like', "%{$comp}%");
                });
            }
        }

        // 8. Filtro de Nombre / Descripción (normaliza acentos y mayúsculas para coincidir con descripcion_normalizada)
        if ($request->filled('q')) {
            $qOriginal = trim((string) $request->input('q'));
            if ($qOriginal !== '') {
                $qNorm = $this->normalizarTextoBusqueda($qOriginal);
                $query->where(function ($sub) use ($qOriginal, $qNorm) {
                    $sub->where('descripcion_normalizada', 'like', "%{$qNorm}%")
                        ->orWhere('descripcion_original', 'like', "%{$qOriginal}%")
                        ->orWhere('observaciones_conciliacion', 'like', "%{$qOriginal}%");
                });
            }
        }

        // 9. Filtro por uno o múltiples IDs de movimiento bancario (ej: "12, 45, 108")
        if ($request->filled('ids_movimiento')) {
            $partes = preg_split('/[\s,]+/', trim((string) $request->input('ids_movimiento')), -1, PREG_SPLIT_NO_EMPTY);
            $idsValidos = array_values(array_unique(array_map(
                'intval',
                array_filter($partes, fn($v) => ctype_digit((string) $v) && (int) $v > 0)
            )));
            if (!empty($idsValidos)) {
                $query->whereIn('id_movimiento_bancario', $idsValidos);
            }
        }

        $perPage = (int) $request->input('per_page', 25);
        $movimientos = $query->orderBy('fecha_operacion', 'desc')
            ->orderBy('id_movimiento_bancario', 'desc')
            ->paginate($perPage);

        // Enriquecemos los ítems para incluir la rendición formateada lista para el frontend
        $items = collect($movimientos->items())->map(function ($m) {
            $item = $m->toArray();

            if ($m->rendicionCobro) {
                $clienteInfo = \App\Modules\Shared\Services\ClienteService::obtenerCliente((int) $m->rendicionCobro->id_cliente);
                $item['rendicion_formateada'] = [
                    'id_rendicion_cobro' => $m->rendicionCobro->id_rendicion_cobro,
                    'fecha' => $m->rendicionCobro->fecha_registro ?? $m->rendicionCobro->fecha ?? null,
                    'monto' => (float) $m->rendicionCobro->monto,
                    'medio_pago' => $m->rendicionCobro->medio_pago,
                    'cliente_nombre' => $clienteInfo['razon_social'] ?? ($clienteInfo['nombre'] ?? "Cliente #{$m->rendicionCobro->id_cliente}"),
                    'cliente_cuit' => $clienteInfo['dni_cuit'] ?? null,
                ];
            } else {
                $item['rendicion_formateada'] = null;
            }

            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'total' => $movimientos->total(),
            'current_page' => $movimientos->currentPage(),
            'per_page' => $movimientos->perPage(),
            'last_page' => $movimientos->lastPage(),
        ]);
    }

    /**
     * GET /api/movimientos-bancarios/{id}
     * Detalle inmutable de un movimiento bancario específico.
     */
    public function show(int $id): JsonResponse
    {
        $movimiento = MovimientoBancario::with('archivoImportado')->find($id);
        if (!$movimiento) {
            return response()->json([
                'error' => true,
                'codigo' => 404,
                'mensaje' => "Movimiento bancario #{$id} no encontrado."
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $movimiento
        ]);
    }

    /**
     * POST /api/extractos/importar
     * Único punto de entrada permitido para cargar movimientos bancarios.
     */
    public function importar(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:20480'], // max 20MB
        ]);

        $archivo = $request->file('archivo');
        $idUsuario = UsuarioActual::id() ?? 1;

        try {
            $res = $this->importService->importar($archivo, (int) $idUsuario);
            return response()->json($res, 201);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'codigo' => 422,
                'mensaje' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Normaliza el texto de búsqueda (mayúsculas y sin tildes) igual que el parser.
     */
    private function normalizarTextoBusqueda(string $texto): string
    {
        $texto = mb_strtoupper($texto, 'UTF-8');
        $texto = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'U', 'N'],
            $texto
        );
        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }
}