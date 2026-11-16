<?php
namespace App\Modules\Entregas\Controllers;

use Exception;
use App\Modules\Shared\Auth\G2Identity;
use App\Modules\Shared\Services\UsuarioService;
use Illuminate\Http\Request;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Http\Response;
use App\Modules\Entregas\Services\EntregaService;
use App\Modules\Entregas\Services\RutaDistanciaService;

class EntregaController extends Controller
{
    private EntregaService $service;

    public function __construct(?EntregaService $service = null)
    {
        $this->service = $service ?? new EntregaService();
    }

    /**
     * GET /api/v1/entregas/pendientes-despacho
     * HU #1: Lista pedidos confirmados pendientes de despacho
     */
    public function pedidosPendientes(Request $request)
    {
        try {
            $idZona = $request->query('zona_id') ? (int)$request->query('zona_id') : null;
            $pedidos = $this->service->obtenerPedidosPendientes($idZona);
            return Response::json($pedidos, 200, "Pedidos pendientes obtenidos con éxito");
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/entregas
     * HU #2: Crear entrega agrupando pedidos y emitiendo remitos
     */
    public function crearEntrega(Request $request)
    {
        $idUsuario = G2Identity::id();
        $esAdmin = G2Identity::role() === 'admin';

        // Un repartidor no puede crear entregas para otro repartidor
        if (! $esAdmin && $request->filled('repartidor_id') && (int) $request->input('repartidor_id') !== $idUsuario) {
            abort(403, 'No podés crear entregas para otro repartidor.');
        }

        $request->validate([
            'repartidor_id' => $esAdmin ? ['required', 'integer', 'min:1'] : ['sometimes', 'integer', 'min:1'],
            'pedidos_ids' => ['required', 'array', 'min:1'],
            'pedidos_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'fecha_salida' => ['sometimes', 'date_format:Y-m-d'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        // Un repartidor solo puede crear entregas asignadas a su propio usuario
        $idRepartidor = $esAdmin ? (int) $request->input('repartidor_id') : $idUsuario;
        abort_unless((UsuarioService::obtenerUsuario($idRepartidor)['rol'] ?? null) === 'repartidor', 422, 'Seleccioná un repartidor activo.');
        try {
            $body = $request->all();
            if (!$body) {
                return Response::error("El cuerpo de la petición no contiene datos válidos.", 400);
            }

            $pedidosIds = (array)($body['pedidos_ids'] ?? []);
            $fechaSalida = (string)($body['fecha_salida'] ?? date('Y-m-d'));
            $observaciones = $body['observaciones'] ?? null;

            $resultado = $this->service->armarEntrega(
                $idRepartidor,
                $pedidosIds,
                $fechaSalida,
                $idUsuario,
                $observaciones
            );

            return Response::json($resultado, 201, $resultado['mensaje']);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/v1/entregas
     * Listar entregas con filtros
     */
    public function listar(Request $request)
    {
        try {
            $filters = $request->query();
            if (G2Identity::role() !== 'admin') {
                $filters['repartidor_id'] = G2Identity::id();
            }
            $entregas = $this->service->listarEntregas($filters);
            return Response::json($entregas, 200);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/entregas/{id}
     * Detalle completo de una entrega
     */
    public function detalle(int|string $id)
    {
        try {
            $idEntrega = (int)$id;
            $entrega = $this->service->obtenerEntrega($idEntrega);
            return Response::json($entrega, 200);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 404);
        }
    }

    /**
     * GET /g2/api/entregas/{id}/ruta-optimizada
     * Ordena los pedidos de la entrega por proximidad desde la distribuidora
     */
    public function rutaOptimizada(int $id, RutaDistanciaService $rutaService)
    {
        try {
            $detalle = $this->service->obtenerEntrega($id);
            $origen = (string) config('g2.distribuidora_direccion', 'Avenida Jose P. Casella 802, Pigüé, Provincia de Buenos Aires, Argentina');
            $resultado = $rutaService->ordenarPedidosPorDistancia($detalle['pedidos'] ?? [], $origen);

            return Response::json($resultado, 200);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/v1/remitos/{id}
     * Consulta y visualización de un remito
     */
    public function verRemito(int|string $id)
    {
        try {
            $idRemito = (int)$id;
            $remito = $this->service->obtenerRemito($idRemito);
            return Response::json($remito, 200);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 404);
        }
    }

    /**
     * GET /api/v1/zonas
     */
    public function zonas(Request $request)
    {
        try {
            $zonas = $this->service->obtenerZonas();
            return Response::json($zonas, 200);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/repartidores
     */
    public function repartidores(Request $request)
    {
        try {
            $repartidores = $this->service->obtenerRepartidores();
            if (G2Identity::role() !== 'admin') {
                $idUsuario = G2Identity::id();
                $repartidores = array_values(array_filter(
                    $repartidores,
                    fn (array $u): bool => (int) ($u['id_usuario'] ?? 0) === $idUsuario
                ));
            }
            return Response::json($repartidores, 200);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/v1/entregas/{id}/pedidos/{idVenta}/entregado
     * POST /api/v1/entregas/confirmar-pedido-entregado
     * HU #3 (Issue #124): Notificación de pedido entregado por el chofer
     */
    public function confirmarPedidoEntregado(Request $request, int|string|null $id = null, int|string|null $idVenta = null)
    {
        try {
            $body = $request->all();
            $idEntrega = (int)($id ?? $body['id_entrega'] ?? 0);
            $idPedido = (int)($idVenta ?? $body['id_venta'] ?? $body['id_pedido'] ?? 0);
            $observaciones = $body['observaciones'] ?? null;
            $medioPago = isset($body['medio_pago']) && $body['medio_pago'] !== ''
                ? strtolower(trim((string) $body['medio_pago']))
                : 'efectivo';
            $montoCobrado = array_key_exists('monto_cobrado', $body) && $body['monto_cobrado'] !== null && $body['monto_cobrado'] !== ''
                ? (float) $body['monto_cobrado']
                : null;
            $idUsuario = G2Identity::id();

            if ($idEntrega <= 0 || $idPedido <= 0) {
                return Response::error("Debe proporcionar id_entrega e id_venta válidos.", 400);
            }

            $resultado = $this->service->confirmarPedidoEntregado(
                $idEntrega,
                $idPedido,
                $observaciones,
                $idUsuario,
                $medioPago,
                $montoCobrado
            );

            return Response::json($resultado, 200, $resultado['mensaje']);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/v1/entregas/pendientes-rendicion
     * Lista entregas con pedidos entregados listas para iniciar rendición
     */
    public function entregasPendientesRendicion(Request $request)
    {
        try {
            $idRepartidor = G2Identity::role() === 'admin' ? ($request->query('id_repartidor') ? (int) $request->query('id_repartidor') : null) : G2Identity::id();
            $entregas = $this->service->obtenerEntregasPendientesRendicion($idRepartidor);
            return Response::json($entregas, 200, "Entregas listas para rendición obtenidas exitosamente.");
        } catch (Exception $e) {
            return Response::error("Error al obtener entregas para rendir: " . $e->getMessage(), 500);
        }
    }
}
