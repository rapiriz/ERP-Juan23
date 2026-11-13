<?php

namespace App\Modules\Entregas\Controllers;

use App\Modules\Entregas\Services\DeliveryPlanningService;
use App\Modules\Entregas\Services\EntregaService;
use App\Modules\Shared\Auth\G2Identity;
use App\Modules\Shared\Http\Controllers\Controller;
use App\Modules\Shared\Http\Response;
use App\Modules\Shared\Services\UsuarioService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class EntregaController extends Controller
{
    public function modificarPedidos(Request $request, int $id, DeliveryPlanningService $planning)
    {
        $data = $request->validate(['pedidos_ids' => ['present', 'array', 'max:100'], 'pedidos_ids.*' => ['integer', 'min:1', 'distinct']]);

        return Response::json($planning->update($id, $data['pedidos_ids'], G2Identity::id()));
    }

    private EntregaService $service;

    public function __construct(?EntregaService $service = null)
    {
        $this->service = $service ?? new EntregaService;
    }

    /**
     * GET /api/v1/entregas/pendientes-despacho
     * HU #1: Lista pedidos confirmados pendientes de despacho
     */
    public function pedidosPendientes(Request $request)
    {
        try {
            $idZona = $request->query('zona_id') ? (int) $request->query('zona_id') : null;
            $pedidos = $this->service->obtenerPedidosPendientes($idZona);

            return Response::json($pedidos, 200, 'Pedidos pendientes obtenidos con éxito');
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
        $request->validate([
            'repartidor_id' => ['required', 'integer', 'min:1'],
            'pedidos_ids' => ['required', 'array', 'min:1'],
            'pedidos_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'fecha_salida' => ['sometimes', 'date_format:Y-m-d'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless((UsuarioService::obtenerUsuario((int) $request->input('repartidor_id'))['rol'] ?? null) === 'repartidor', 422, 'Seleccioná un repartidor activo.');
        try {
            $body = $request->all();
            if (! $body) {
                return Response::error('El cuerpo de la petición no contiene datos válidos.', 400);
            }

            $idRepartidor = (int) ($body['repartidor_id'] ?? 0);
            $pedidosIds = (array) ($body['pedidos_ids'] ?? []);
            $fechaSalida = (string) ($body['fecha_salida'] ?? date('Y-m-d'));
            $observaciones = $body['observaciones'] ?? null;
            $idUsuario = G2Identity::id();

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
            $idEntrega = (int) $id;
            $entrega = $this->service->obtenerEntrega($idEntrega);

            return Response::json($entrega, 200);
        } catch (Exception $e) {
            return Response::error($e->getMessage(), 404);
        }
    }

    /**
     * GET /api/v1/remitos/{id}
     * Consulta y visualización de un remito
     */
    public function verRemito(int|string $id)
    {
        try {
            $idRemito = (int) $id;
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
        $collection = $request->validate([
            'monto_cobrado' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'medio_pago' => ['sometimes', 'required', 'in:efectivo,transferencia,cheque'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $body = $request->all();
            $idEntrega = (int) ($id ?? $body['id_entrega'] ?? 0);
            $idPedido = (int) ($idVenta ?? $body['id_venta'] ?? $body['id_pedido'] ?? 0);
            $observaciones = $body['observaciones'] ?? null;
            $idUsuario = G2Identity::id();

            if ($idEntrega <= 0 || $idPedido <= 0) {
                return Response::error('Debe proporcionar id_entrega e id_venta válidos.', 400);
            }

            $resultado = $this->service->confirmarPedidoEntregado(
                $idEntrega,
                $idPedido,
                $observaciones,
                $idUsuario,
                isset($collection['monto_cobrado']) ? (float) $collection['monto_cobrado'] : null,
                $collection['medio_pago'] ?? 'efectivo'
            );

            return Response::json($resultado, 200, $resultado['mensaje']);
        } catch (ValidationException|HttpExceptionInterface $e) {
            throw $e;
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

            return Response::json($entregas, 200, 'Entregas listas para rendición obtenidas exitosamente.');
        } catch (Exception $e) {
            return Response::error('Error al obtener entregas para rendir: '.$e->getMessage(), 500);
        }
    }
}
