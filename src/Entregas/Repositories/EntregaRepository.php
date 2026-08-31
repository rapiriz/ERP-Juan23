<?php
namespace Entregas\Repositories;

use PDO;
use Shared\Database\Conexion;
use Entregas\Models\Entrega;

class EntregaRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Conexion::obtener();
    }

    /**
     * HU #1: Obtener pedidos confirmados que no han sido asignados a ninguna entrega activa
     */
    public function obtenerPedidosConfirmadosPendientes(?int $idZona = null): array
    {
        $sql = "
            SELECT 
                v.id_venta,
                v.fecha,
                v.total,
                v.total_bultos,
                v.total_sueltos,
                v.observaciones,
                c.id_cliente,
                c.nombre AS cliente_nombre,
                c.razon_social,
                c.direccion,
                c.localidad,
                c.telefono,
                z.id_zona,
                z.nombre AS zona_nombre
            FROM venta v
            INNER JOIN cliente c ON v.id_cliente = c.id_cliente
            INNER JOIN zona z ON c.id_zona = z.id_zona
            WHERE v.estado = 'confirmada'
              AND v.id_venta NOT IN (
                  SELECT ep.id_venta 
                  FROM entrega_pedido ep
                  INNER JOIN entrega e ON ep.id_entrega = e.id_entrega
                  WHERE e.estado IN ('en_preparacion', 'en_camino')
              )
        ";

        $params = [];
        if ($idZona !== null && $idZona > 0) {
            $sql .= " AND z.id_zona = :id_zona ";
            $params[':id_zona'] = $idZona;
        }

        $sql .= " ORDER BY z.nombre ASC, v.fecha ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener detalle de productos de una venta específica
     */
    public function obtenerDetalleVenta(int $idVenta): array
    {
        $sql = "
            SELECT 
                dv.id_detalle_venta,
                dv.id_producto,
                p.codigo,
                p.nombre AS producto_nombre,
                p.descripcion AS producto_descripcion,
                dv.cantidad,
                dv.precio_unitario,
                dv.subtotal,
                dv.bultos,
                dv.unidades_sueltas
            FROM detalle_venta dv
            INNER JOIN producto p ON dv.id_producto = p.id_producto
            WHERE dv.id_venta = :id_venta
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_venta' => $idVenta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insertar nueva Entrega
     */
    public function guardarEntrega(Entrega $entrega): int
    {
        $sql = "
            INSERT INTO entrega (
                id_repartidor, 
                fecha_salida, 
                estado, 
                observaciones, 
                id_usuario_creacion
            ) VALUES (
                :id_repartidor, 
                :fecha_salida, 
                :estado, 
                :observaciones, 
                :id_usuario_creacion
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_repartidor' => $entrega->idRepartidor,
            ':fecha_salida' => $entrega->fechaSalida,
            ':estado' => $entrega->estado,
            ':observaciones' => $entrega->observaciones,
            ':id_usuario_creacion' => $entrega->idUsuarioCreacion
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Vincular un pedido a una Entrega
     */
    public function vincularPedidoAEntrega(int $idEntrega, int $idVenta, string $estadoPedido = 'en_preparacion'): void
    {
        $sql = "
            INSERT INTO entrega_pedido (
                id_entrega, 
                id_venta, 
                estado_pedido_entrega
            ) VALUES (
                :id_entrega, 
                :id_venta, 
                :estado_pedido_entrega
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_entrega' => $idEntrega,
            ':id_venta' => $idVenta,
            ':estado_pedido_entrega' => $estadoPedido
        ]);
    }

    /**
     * Actualizar estado de una venta (ej. 'en_preparacion', 'en_camino')
     */
    public function actualizarEstadoVenta(int $idVenta, string $nuevoEstado): void
    {
        $sql = "UPDATE venta SET estado = :estado WHERE id_venta = :id_venta";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':estado' => $nuevoEstado,
            ':id_venta' => $idVenta
        ]);
    }

    /**
     * Listar entregas realizadas o en curso
     */
    public function listarEntregas(array $filtros = []): array
    {
        $sql = "
            SELECT 
                e.id_entrega,
                e.fecha_creacion,
                e.fecha_salida,
                e.estado,
                e.observaciones,
                u.nombre AS repartidor_nombre,
                COUNT(DISTINCT ep.id_venta) AS cantidad_pedidos,
                COALESCE(SUM(v.total_bultos), 0) AS total_bultos,
                COALESCE(SUM(v.total), 0) AS total_dinero
            FROM entrega e
            INNER JOIN usuario u ON e.id_repartidor = u.id_usuario
            LEFT JOIN entrega_pedido ep ON e.id_entrega = ep.id_entrega
            LEFT JOIN venta v ON ep.id_venta = v.id_venta
            WHERE 1=1
        ";

        $params = [];
        if (!empty($filtros['repartidor_id'])) {
            $sql .= " AND e.id_repartidor = :repartidor_id ";
            $params[':repartidor_id'] = $filtros['repartidor_id'];
        }
        if (!empty($filtros['fecha'])) {
            $sql .= " AND e.fecha_salida = :fecha ";
            $params[':fecha'] = $filtros['fecha'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND e.estado = :estado ";
            $params[':estado'] = $filtros['estado'];
        }

        $sql .= " GROUP BY e.id_entrega ORDER BY e.id_entrega DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener detalle completo de una Entrega
     */
    public function obtenerEntregaPorId(int $idEntrega): ?array
    {
        $sql = "
            SELECT 
                e.*,
                u.nombre AS repartidor_nombre,
                uc.nombre AS creador_nombre
            FROM entrega e
            INNER JOIN usuario u ON e.id_repartidor = u.id_usuario
            INNER JOIN usuario uc ON e.id_usuario_creacion = uc.id_usuario
            WHERE e.id_entrega = :id_entrega
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_entrega' => $idEntrega]);
        $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entrega) {
            return null;
        }

        // Obtener pedidos incluidos
        $sqlPedidos = "
            SELECT 
                v.id_venta,
                v.fecha AS fecha_venta,
                v.total,
                v.total_bultos,
                v.observaciones AS observaciones_venta,
                ep.estado_pedido_entrega,
                ep.motivo_rechazo,
                c.id_cliente,
                c.nombre AS cliente_nombre,
                c.direccion,
                c.localidad,
                c.telefono,
                z.nombre AS zona_nombre,
                r.id_remito,
                r.numero_remito,
                r.estado AS estado_remito
            FROM entrega_pedido ep
            INNER JOIN venta v ON ep.id_venta = v.id_venta
            INNER JOIN cliente c ON v.id_cliente = c.id_cliente
            INNER JOIN zona z ON c.id_zona = z.id_zona
            LEFT JOIN remito r ON r.id_entrega = ep.id_entrega AND r.id_venta = v.id_venta
            WHERE ep.id_entrega = :id_entrega
            ORDER BY z.nombre, c.nombre
        ";
        $stmtPedidos = $this->db->prepare($sqlPedidos);
        $stmtPedidos->execute([':id_entrega' => $idEntrega]);
        $entrega['pedidos'] = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

        return $entrega;
    }

    /**
     * Listar Zonas
     */
    public function listarZonas(): array
    {
        $stmt = $this->db->query("SELECT * FROM zona ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar Repartidores
     */
    public function listarRepartidores(): array
    {
        $stmt = $this->db->query("SELECT id_usuario, nombre, user FROM usuario WHERE rol = 'repartidor' AND estado = 'activo' ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
