<?php
namespace App\Entregas\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\Response;
use App\Entregas\Services\EntregaService;

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
    public function pedidosPendientes(Request $request): void
    {
        try {
            $idZona = $request->query('zona_id') ? (int)$request->query('zona_id') : null;
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
    public function crearEntrega(Request $request): void
    {
        try {
            $body = $request->all();
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
    public function listar(Request $request): void
    {
        try {
            $entregas = $this->service->listarEntregas($request->query());
            Response::json($entregas, 200);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/entregas/{id}
     * Detalle completo de una entrega
     */
    public function detalle(int|string $id): void
    {
        try {
            $idEntrega = (int)$id;
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
    public function verRemito(int|string $id): void
    {
        try {
            $idRemito = (int)$id;
            $remito = $this->service->obtenerRemito($idRemito);
            Response::json($remito, 200);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    /**
     * GET /api/v1/zonas
     */
    public function zonas(Request $request): void
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
    public function repartidores(Request $request): void
    {
        try {
            $repartidores = $this->service->obtenerRepartidores();
            Response::json($repartidores, 200);
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}

