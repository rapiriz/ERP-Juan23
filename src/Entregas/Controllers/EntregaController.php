<?php
namespace Entregas\Controllers;

use Exception;
use Shared\Http\Response;
use Entregas\Services\EntregaService;

class EntregaController
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
    public function pedidosPendientes(array $params = [], ?array $body = null, array $query = []): void
    {
        try {
            $idZona = isset($query['zona_id']) && $query['zona_id'] !== '' ? (int)$query['zona_id'] : null;
            $pedidos = $this->service->obtenerPedidosPendientes($idZona);
            Response::json($pedidos, 200, "Pedidos pendientes obtenidos con éxito");
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/entregas
     * HU #2: Crear entrega agrupando pedidos y emitiendo remitos
     */
    public function crearEntrega(array $params = [], ?array $body = null, array $query = []): void
    {
        try {
            if (!$body) {
                Response::error("El cuerpo de la petición no contiene datos válidos.", 400);
                return;
            }

            $idRepartidor = (int)($body['repartidor_id'] ?? 0);
            $pedidosIds = (array)($body['pedidos_ids'] ?? []);
            $fechaSalida = (string)($body['fecha_salida'] ?? date('Y-m-d'));
            $observaciones = $body['observaciones'] ?? null;
            $idUsuario = (int)($body['usuario_id'] ?? 1);

            $resultado = $this->service->armarEntrega(
                $idRepartidor,
                $pedidosIds,
                $fechaSalida,
                $idUsuario,
                $observaciones
            );

            Response::json($resultado, 201, $resultado['mensaje']);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/v1/entregas
     * Listar entregas con filtros
     */
    public function listar(array $params = [], ?array $body = null, array $query = []): void
    {
        try {
            $entregas = $this->service->listarEntregas($query);
            Response::json($entregas, 200);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/entregas/{id}
     * Detalle completo de una entrega
     */
    public function detalle(array $params = [], ?array $body = null, array $query = []): void
    {
        try {
            $idEntrega = (int)($params['id'] ?? 0);
            $entrega = $this->service->obtenerEntrega($idEntrega);
            Response::json($entrega, 200);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    /**
     * GET /api/v1/remitos/{id}
     * Consulta y visualización de un remito
     */
    public function verRemito(array $params = [], ?array $body = null, array $query = []): void
    {
        try {
            $idRemito = (int)($params['id'] ?? 0);
            $remito = $this->service->obtenerRemito($idRemito);
            Response::json($remito, 200);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    /**
     * GET /api/v1/zonas
     */
    public function zonas(array $params = [], ?array $body = null, array $query = []): void
    {
        try {
            $zonas = $this->service->obtenerZonas();
            Response::json($zonas, 200);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/repartidores
     */
    public function repartidores(array $params = [], ?array $body = null, array $query = []): void
    {
        try {
            $repartidores = $this->service->obtenerRepartidores();
            Response::json($repartidores, 200);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
