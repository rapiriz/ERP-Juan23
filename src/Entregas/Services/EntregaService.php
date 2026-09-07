<?php
namespace App\Entregas\Services;

use Exception;
use App\Shared\Database\Conexion;
use App\Entregas\Models\Entrega;
use App\Entregas\Models\Remito;
use App\Entregas\Repositories\EntregaRepository;
use App\Entregas\Repositories\RemitoRepository;

/**
 * Servicio de Negocio: Entregas y Remitos
 * Contiene las reglas de negocio de HU #1 y HU #2
 */
class EntregaService
{
    private EntregaRepository $entregaRepo;
    private RemitoRepository $remitoRepo;

    public function __construct(?EntregaRepository $entregaRepo = null, ?RemitoRepository $remitoRepo = null)
    {
        $this->entregaRepo = $entregaRepo ?? new EntregaRepository();
        $this->remitoRepo = $remitoRepo ?? new RemitoRepository();
    }

    /**
     * HU #1: Visualizar listado de pedidos confirmados listos para despacho
     */
    public function obtenerPedidosPendientes(?int $idZona = null): array
    {
        $pedidos = $this->entregaRepo->obtenerPedidosConfirmadosPendientes($idZona);

        // Enriquecer cada pedido con el detalle de sus productos
        foreach ($pedidos as &$pedido) {
            $pedido['items'] = $this->entregaRepo->obtenerDetalleVenta((int)$pedido['id_venta']);
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
        int $idUsuarioCreacion = 1,
        ?string $observaciones = null
    ): array {
        if (empty($pedidosIds)) {
            throw new Exception("Debe seleccionar al menos un pedido para armar la entrega.");
        }

        if ($idRepartidor <= 0) {
            throw new Exception("Debe asignar un repartidor válido.");
        }

        if (empty($fechaSalida)) {
            $fechaSalida = date('Y-m-d');
        }

        // 1. Iniciar transacción atómica
        Conexion::iniciarTransaccion();

        try {
            // 2. Crear cabecera de la Entrega (Viaje en preparación)
            $entrega = new Entrega(
                $idRepartidor,
                $fechaSalida,
                $idUsuarioCreacion,
                'en_preparacion',
                $observaciones
            );

            $idEntrega = $this->entregaRepo->guardarEntrega($entrega);
            $remitosCreados = [];

            // 3. Procesar cada pedido seleccionado
            foreach ($pedidosIds as $idVenta) {
                $idVenta = (int)$idVenta;

                // A. Vincular pedido a la entrega
                $this->entregaRepo->vincularPedidoAEntrega($idEntrega, $idVenta, 'en_preparacion');

                // B. Cambiar estado del pedido a 'en_preparacion'
                $this->entregaRepo->actualizarEstadoVenta($idVenta, 'en_preparacion');

                // C. Obtener productos y bultos del pedido
                $items = $this->entregaRepo->obtenerDetalleVenta($idVenta);
                if (empty($items)) {
                    throw new Exception("El pedido #{$idVenta} no tiene productos asociados.");
                }

                // D. Generar número de remito correlativo oficial
                $numeroRemito = $this->remitoRepo->generarSiguienteNumeroRemito(1);

                // E. Guardar cabecera de Remito
                $remito = new Remito(
                    $numeroRemito,
                    $idEntrega,
                    $idVenta,
                    'emitido',
                    "Remito generado para entrega #{$idEntrega}"
                );
                $idRemito = $this->remitoRepo->guardarRemito($remito);

                // F. Guardar detalle físico del Remito
                $this->remitoRepo->guardarDetalleRemito($idRemito, $items);

                $remitosCreados[] = [
                    'id_remito' => $idRemito,
                    'numero_remito' => $numeroRemito,
                    'id_venta' => $idVenta
                ];
            }

            // 4. Confirmar todos los cambios
            Conexion::confirmar();

            return [
                'id_entrega' => $idEntrega,
                'estado' => 'en_preparacion',
                'fecha_salida' => $fechaSalida,
                'cantidad_pedidos' => count($pedidosIds),
                'remitos' => $remitosCreados,
                'mensaje' => "Entrega #{$idEntrega} creada con éxito y " . count($remitosCreados) . " remitos emitidos."
            ];
        } catch (Exception $e) {
            Conexion::revertir();
            throw new Exception("Error al armar la entrega: " . $e->getMessage());
        }
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
        if (!$entrega) {
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
        if (!$remito) {
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
}
