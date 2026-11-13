<?php

namespace App\Modules\Entregas\Services;

use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Entregas\Models\Entrega;
use App\Modules\Entregas\Models\Remito;
use App\Modules\Entregas\Repositories\EntregaRepository;
use App\Modules\Entregas\Repositories\RemitoRepository;
use Exception;

/**
 * Servicio de Negocio: Entregas y Remitos
 * Contiene las reglas de negocio de HU #1 y HU #2
 */
class EntregaService
{
    private EntregaRepository $entregaRepo;

    private RemitoRepository $remitoRepo;

    private VentasApiClient $ventasClient;

    public function __construct(
        ?EntregaRepository $entregaRepo = null,
        ?RemitoRepository $remitoRepo = null,
        ?VentasApiClient $ventasClient = null
    ) {
        $this->entregaRepo = $entregaRepo ?? new EntregaRepository;
        $this->remitoRepo = $remitoRepo ?? new RemitoRepository;
        $this->ventasClient = $ventasClient ?? new VentasApiClient;
    }

    /**
     * HU #1: Visualizar listado de pedidos confirmados listos para despacho
     */
    public function obtenerPedidosPendientes(?int $idZona = null): array
    {
        $pedidos = $this->entregaRepo->obtenerPedidosConfirmadosPendientes($idZona);

        // Enriquecer cada pedido con el detalle de sus productos
        foreach ($pedidos as &$pedido) {
            $pedido['items'] = $this->entregaRepo->obtenerDetalleVenta((int) $pedido['id_venta']);
        }

        return $pedidos;
    }

    /**
     * HU #2: Armado de entrega y emisión de remitos oficiales
     * Ejecuta una transacción atómica (todo o nada)
     */
    public function armarEntrega(
        int $idRepartidor,
        array $pedidosIds,
        string $fechaSalida,
        int $idUsuarioCreacion,
        ?string $observaciones = null
    ): array {
        return app(DeliveryPlanningService::class)->create($idRepartidor, $pedidosIds, $fechaSalida, $idUsuarioCreacion, $observaciones);
    }

    /**
     * Listar entregas
     */
    public function listarEntregas(array $filtros = []): array
    {
        return $this->entregaRepo->listarEntregas($filtros);
    }

    /**
     * Obtener detalle de una entrega
     */
    public function obtenerEntrega(int $idEntrega): ?array
    {
        $entrega = $this->entregaRepo->obtenerEntregaPorId($idEntrega);
        if (! $entrega) {
            throw new Exception("La entrega #{$idEntrega} no existe.");
        }

        return $entrega;
    }

    /**
     * Obtener Remito para visualización / impresión
     */
    public function obtenerRemito(int $idRemito): ?array
    {
        $remito = $this->remitoRepo->obtenerRemitoPorId($idRemito);
        if (! $remito) {
            throw new Exception("El remito #{$idRemito} no existe.");
        }

        return $remito;
    }

    public function obtenerZonas(): array
    {
        return $this->entregaRepo->listarZonas();
    }

    public function obtenerRepartidores(): array
    {
        return $this->entregaRepo->listarRepartidores();
    }

    /**
     * HU #3 (Issue #124): Notificar y confirmar pedido entregado
     * Permite al chofer marcar un pedido como completado en tiempo real
     */
    public function confirmarPedidoEntregado(
        int $idEntrega,
        int $idVenta,
        ?string $observaciones,
        int $idUsuarioAuditoria,
        ?float $montoCobrado = null,
        string $medioPago = 'efectivo'
    ): array {
        return app(DeliveryPlanningService::class)->deliver($idEntrega, $idVenta, $idUsuarioAuditoria, $observaciones, $montoCobrado, $medioPago);
    }

    /**
     * Obtener entregas con pedidos entregados listas para rendición
     */
    public function obtenerEntregasPendientesRendicion(?int $idRepartidor = null): array
    {
        return $this->entregaRepo->obtenerEntregasListasParaRendicion($idRepartidor);
    }
}
