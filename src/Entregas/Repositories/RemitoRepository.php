<?php
namespace App\Entregas\Repositories;

use PDO;
use App\Shared\Database\Conexion;
use App\Entregas\Models\Remito;

class RemitoRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Conexion::obtener();
    }

    /**
     * Generar el siguiente número correlativo de remito oficial (Formato: R-0001-00000001)
     */
    public function generarSiguienteNumeroRemito(int $puntoVenta = 1): string
    {
        $pvPrefijo = sprintf("R-%04d-", $puntoVenta);
        
        $sql = "
            SELECT numero_remito 
            FROM remito 
            WHERE numero_remito LIKE :prefijo 
            ORDER BY id_remito DESC 
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':prefijo' => $pvPrefijo . '%']);
        $ultimo = $stmt->fetchColumn();

        $siguienteNumero = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo);
            if (isset($partes[2])) {
                $siguienteNumero = ((int)$partes[2]) + 1;
            }
        }

        return sprintf("R-%04d-%08d", $puntoVenta, $siguienteNumero);
    }

    /**
     * Guardar cabecera de Remito
     */
    public function guardarRemito(Remito $remito): int
    {
        $sql = "
            INSERT INTO remito (
                numero_remito, 
                id_entrega, 
                id_venta, 
                estado, 
                observaciones
            ) VALUES (
                :numero_remito, 
                :id_entrega, 
                :id_venta, 
                :estado, 
                :observaciones
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':numero_remito' => $remito->numeroRemito,
            ':id_entrega' => $remito->idEntrega,
            ':id_venta' => $remito->idVenta,
            ':estado' => $remito->estado,
            ':observaciones' => $remito->observaciones
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Guardar detalle de artículos del Remito
     */
    public function guardarDetalleRemito(int $idRemito, array $items): void
    {
        $sql = "
            INSERT INTO detalle_remito (
                id_remito, 
                id_producto, 
                descripcion, 
                cantidad, 
                bultos
            ) VALUES (
                :id_remito, 
                :id_producto, 
                :descripcion, 
                :cantidad, 
                :bultos
            )
        ";
        $stmt = $this->db->prepare($sql);

        foreach ($items as $item) {
            $stmt->execute([
                ':id_remito' => $idRemito,
                ':id_producto' => $item['id_producto'],
                ':descripcion' => $item['producto_nombre'] ?? $item['descripcion'] ?? 'Producto',
                ':cantidad' => (int)$item['cantidad'],
                ':bultos' => (int)($item['bultos'] ?? 1)
            ]);
        }
    }

    /**
     * Obtener Remito completo por su ID
     */
    public function obtenerRemitoPorId(int $idRemito): ?array
    {
        $sql = "
            SELECT 
                r.*,
                v.fecha AS fecha_venta,
                v.total AS total_venta,
                v.total_bultos,
                v.total_sueltos,
                c.nombre AS cliente_nombre,
                c.razon_social,
                c.cuit,
                c.direccion,
                c.localidad,
                c.telefono,
                z.nombre AS zona_nombre,
                u.nombre AS repartidor_nombre
            FROM remito r
            INNER JOIN venta v ON r.id_venta = v.id_venta
            INNER JOIN cliente c ON v.id_cliente = c.id_cliente
            INNER JOIN zona z ON c.id_zona = z.id_zona
            INNER JOIN entrega e ON r.id_entrega = e.id_entrega
            INNER JOIN usuario u ON e.id_repartidor = u.id_usuario
            WHERE r.id_remito = :id_remito
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_remito' => $idRemito]);
        $remito = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$remito) {
            return null;
        }

        // Obtener líneas del remito
        $sqlDetalle = "
            SELECT dr.*, p.codigo
            FROM detalle_remito dr
            LEFT JOIN producto p ON dr.id_producto = p.id_producto
            WHERE dr.id_remito = :id_remito
        ";
        $stmtDet = $this->db->prepare($sqlDetalle);
        $stmtDet->execute([':id_remito' => $idRemito]);
        $remito['items'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        return $remito;
    }
}
