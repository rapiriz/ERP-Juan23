<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // ORM de Laravel para representar la cabecera de una venta.

/**
 * Modelo Venta  ->  tabla `venta`
 *
 * Cabecera de una venta (cliente, fecha, total, estado, usuario).
 * estado: 'pendiente' | 'confirmada' | 'pagada' | 'facturada' | 'cancelada'.
 * Desde el POS se crea 'pagada' (cobrada completa) o 'confirmada' (queda deuda).
 * id_cliente NULL = Consumidor Final.
 * USADO POR: VentaService, CajaService.
 *
 * $guarded = [] : permite create([...]) con cualquier columna. Es seguro
 * porque este modelo SOLO se escribe desde los Services (con datos ya
 * validados), jamás directamente con lo que llega del request.
 *
 * IMPORTANCIA Y CONEXIONES:
 *   Es la cabecera que agrupa una operación de venta. VentaService la crea
 *   dentro de una transacción y luego usa id_venta para asociar detalles,
 *   movimientos de stock, cobros, auditoría y la tabla cobro_venta.
 *   CajaService consulta fecha, usuario, estado y total para el arqueo diario.
 *
 * CONEXIÓN CON EL FRONT:
 *   El navegador no crea ni actualiza este modelo directamente. Envía el cobro
 *   a POST /ventas/cobrar; VentaController devuelve un resumen bajo "venta"
 *   (id, estado, importes y comprobantes), no el modelo Eloquent completo ni sus
 *   relaciones. No hay aquí una ruta de historial/detalle de ventas.
 *
 * En el POS se crean estados "pagada" o "confirmada". Facturación y anulación
 * no forman parte del flujo implementado actualmente.
 */
class Venta extends Model // Representa una fila de la tabla venta (cabecera).
{
    protected $table = 'venta'; // Nombre real de la tabla.
    protected $primaryKey = 'id_venta'; // Identificador que usan detalles y movimientos relacionados.
    public $timestamps = false; // Eloquent no debe asumir created_at/updated_at en esta tabla.
    protected $guarded = []; // Asignación masiva abierta: crear/actualizar solo con datos confiables del servicio.

    /**
     * Una venta tiene muchas líneas en detalle_venta.
     * Las claves explícitas indican FK y PK cuando no siguen la convención id.
     */
    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }

    /**
     * La venta pertenece a un cliente. id_cliente NULL representa Consumidor Final,
     * por lo que en ese caso esta relación devuelve null.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }
}
