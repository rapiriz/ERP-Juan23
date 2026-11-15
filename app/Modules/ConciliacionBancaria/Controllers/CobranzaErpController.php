<?php

namespace App\Modules\ConciliacionBancaria\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Shared\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobranzaErpController extends Controller
{
    private const ESTADOS_VALIDOS = ['aprobada', 'pendiente', 'rechazada'];
    private const MEDIOS_VALIDOS = ['transferencia', 'cheque'];

    private array $clientesCache = [];

    /**
     * GET /api/cobranzas-erp
     * Consulta cobros reales de RENDICION_COBRO unida a RENDICION con soporte
     * para filtros múltiples por fecha, estado de rendición, medio de pago,
     * rango de importes positivos, cliente (nombre/CUIT) e IDs de cobro.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RendicionCobro::query()
            ->join('RENDICION', 'RENDICION_COBRO.id_rendicion', '=', 'RENDICION.id_rendicion')
            ->select(
                'RENDICION_COBRO.*',
                'RENDICION.fecha as rendicion_fecha',
                'RENDICION.estado as rendicion_estado',
                'RENDICION.observaciones as rendicion_obs'
            );

        // 1. Rango de fechas de registro del cobro
        if ($request->filled('fecha_desde')) {
            $query->whereDate('RENDICION_COBRO.fecha_registro', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('RENDICION_COBRO.fecha_registro', '<=', $request->input('fecha_hasta'));
        }

        // 2. Estado de la rendición (validado contra ESTADOS_VALIDOS)
        if ($request->has('estado')) {
            $estadosRaw = $request->input('estado');
            if ($estadosRaw === '__ninguno__' || $estadosRaw === '') {
                $query->whereRaw('1 = 0');
            } else {
                $estados = is_array($estadosRaw)
                    ? $estadosRaw
                    : array_filter(array_map('trim', explode(',', (string) $estadosRaw)));
                $estadosFiltrados = array_values(array_intersect($estados, self::ESTADOS_VALIDOS));

                if (empty($estadosFiltrados)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('RENDICION.estado', $estadosFiltrados);
                }
            }
        }

        // 3. Medio de pago (validado contra MEDIOS_VALIDOS)
        if ($request->has('medio_pago')) {
            $mediosRaw = $request->input('medio_pago');
            if ($mediosRaw === '__ninguno__' || $mediosRaw === '') {
                $query->whereRaw('1 = 0');
            } else {
                $medios = is_array($mediosRaw)
                    ? $mediosRaw
                    : array_filter(array_map('trim', explode(',', (string) $mediosRaw)));
                $mediosFiltrados = array_values(array_intersect($medios, self::MEDIOS_VALIDOS));

                if (empty($mediosFiltrados)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('RENDICION_COBRO.medio_pago', $mediosFiltrados);
                }
            }
        }

        // 4. Rango de importes (solo positivos)
        if ($request->filled('monto_min') && is_numeric($request->input('monto_min'))) {
            $query->where('RENDICION_COBRO.monto', '>=', max(0, (float) $request->input('monto_min')));
        }
        if ($request->filled('monto_max') && is_numeric($request->input('monto_max'))) {
            $query->where('RENDICION_COBRO.monto', '<=', max(0, (float) $request->input('monto_max')));
        }

        // 5. Filtro por uno o múltiples IDs de cobro (ej: "5, 89, 1234, 67")
        if ($request->filled('ids_cobro')) {
            $partes = preg_split('/[\s,]+/', trim((string) $request->input('ids_cobro')), -1, PREG_SPLIT_NO_EMPTY);
            $idsValidos = array_values(array_unique(array_map(
                'intval',
                array_filter($partes, fn($v) => ctype_digit((string) $v) && (int) $v > 0)
            )));
            if (!empty($idsValidos)) {
                $query->whereIn('RENDICION_COBRO.id_rendicion_cobro', $idsValidos);
            }
        }

        $query->orderBy('RENDICION_COBRO.fecha_registro', 'desc')
            ->orderBy('RENDICION_COBRO.id_rendicion_cobro', 'desc');

        $perPage = (int) $request->input('per_page', 25);
        $page = max(1, (int) $request->input('page', 1));

        // 6. Filtro por nombre/razón social o CUIT del cliente (con caché en memoria)
        if ($request->filled('cliente_q')) {
            $busqueda = $this->normalizarTexto((string) $request->input('cliente_q'));
            $busquedaDigitos = preg_replace('/\D/', '', $busqueda);

            $todos = $query->get();
            $filtrados = [];

            foreach ($todos as $c) {
                $clienteInfo = $this->obtenerClienteCacheado((int) $c->id_cliente);
                $nombre = $clienteInfo['razon_social'] ?? ($clienteInfo['nombre'] ?? "Cliente #{$c->id_cliente}");
                $cuitCliente = preg_replace('/\D/', '', (string) ($clienteInfo['dni_cuit'] ?? ''));

                $coincideNombre = $busqueda !== '' && str_contains($this->normalizarTexto($nombre), $busqueda);
                $coincideCuit = $busquedaDigitos !== '' && $cuitCliente !== '' && str_contains($cuitCliente, $busquedaDigitos);

                if ($coincideNombre || $coincideCuit) {
                    $filtrados[] = $this->formatearItem($c, $clienteInfo);
                }
            }

            $total = count($filtrados);
            $lastPage = max(1, (int) ceil($total / $perPage));
            $items = array_slice($filtrados, ($page - 1) * $perPage, $perPage);

            return response()->json([
                'success' => true,
                'data' => $items,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ]);
        }

        // Paginación normal cuando no hay búsqueda por cliente
        $cobros = $query->paginate($perPage);
        $items = collect($cobros->items())->map(function ($c) {
            $clienteInfo = $this->obtenerClienteCacheado((int) $c->id_cliente);
            return $this->formatearItem($c, $clienteInfo);
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'total' => $cobros->total(),
            'current_page' => $cobros->currentPage(),
            'per_page' => $cobros->perPage(),
            'last_page' => $cobros->lastPage(),
        ]);
    }

    /**
     * GET /api/cobranzas-erp/{id}
     */
    public function show(int $id): JsonResponse
    {
        $cobro = RendicionCobro::with('rendicion')->find($id);
        if (!$cobro) {
            return response()->json([
                'error' => true,
                'codigo' => 404,
                'mensaje' => "Cobranza de rendición #{$id} no encontrada."
            ], 404);
        }

        $clienteInfo = $this->obtenerClienteCacheado((int) $cobro->id_cliente);

        return response()->json([
            'success' => true,
            'data' => [
                'id_rendicion_cobro' => $cobro->id_rendicion_cobro,
                'id_rendicion' => $cobro->id_rendicion,
                'id_cliente' => $cobro->id_cliente,
                'cliente_nombre' => $clienteInfo['razon_social'] ?? ($clienteInfo['nombre'] ?? "Cliente #{$cobro->id_cliente}"),
                'cliente_cuit' => $clienteInfo['dni_cuit'] ?? null,
                'id_factura' => $cobro->id_factura,
                'monto' => (float) $cobro->monto,
                'medio_pago' => $cobro->medio_pago,
                'fecha_registro' => $cobro->fecha_registro,
                'rendicion' => $cobro->rendicion,
            ]
        ]);
    }

    private function obtenerClienteCacheado(int $idCliente): ?array
    {
        if (!array_key_exists($idCliente, $this->clientesCache)) {
            $this->clientesCache[$idCliente] = ClienteService::obtenerCliente($idCliente);
        }
        return $this->clientesCache[$idCliente];
    }

    private function formatearItem($c, ?array $clienteInfo): array
    {
        return [
            'id' => $c->id_rendicion_cobro,
            'id_rendicion_cobro' => $c->id_rendicion_cobro,
            'id_rendicion' => $c->id_rendicion,
            'id_cliente' => $c->id_cliente,
            'cliente_nombre' => $clienteInfo['razon_social'] ?? ($clienteInfo['nombre'] ?? "Cliente #{$c->id_cliente}"),
            'cliente_cuit' => $clienteInfo['dni_cuit'] ?? null,
            'id_factura' => $c->id_factura,
            'monto' => (float) $c->monto,
            'monto_total' => (float) $c->monto,
            'medio_pago' => $c->medio_pago,
            'fecha' => $c->fecha_registro,
            'estado' => $c->rendicion_estado,
            'observaciones' => $c->rendicion_obs,
        ];
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto), 'UTF-8');
        return str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'U', 'N'],
            $texto
        );
    }
}