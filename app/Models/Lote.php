<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // ORM de Laravel para consultar y guardar filas.

/**
 * Modelo Lote  ->  tabla `lote`
 *
 * Lote de un producto con fecha de vencimiento.
 * Al vender se descuenta primero el lote que vence antes (criterio FEFO).
 * USADO POR: VentaService.
 *
 * $guarded = [] : permite create([...]) con cualquier columna. Es seguro
 * porque este modelo SOLO se escribe desde los Services (con datos ya
 * validados), jamás directamente con lo que llega del request.
 *
 * CONEXIONES:
 *   VentaService::descontarLotes() consulta este modelo durante el cobro,
 *   filtra lotes vigentes/no vencidos y descuenta primero el vencimiento más
 *   cercano (FEFO). Si la cantidad del lote llega a cero, marca su estado como
 *   consumido. Producto.stock se descuenta por separado en el mismo proceso.
 *
 * FRONT:
 *   No hay rutas de lotes para el POS y el navegador no debe elegir el lote.
 *   El front trabaja con el stock general de Producto; el servidor elige los
 *   lotes al confirmar la venta.
 *
 * LIMITACIÓN A TENER PRESENTE:
 *   VentaService no comprueba al final que los lotes elegibles cubran toda la
 *   cantidad vendida. Si faltan unidades en lotes, hoy el bucle puede terminar
 *   dejando unidades sin descontar de lote aunque Producto.stock baje completo.
 *   Es una validación pendiente del servicio/esquema, no del frontend.
 */
class Lote extends Model // Representa un lote de inventario almacenado en la tabla lote.
{
    protected $table = 'lote'; // Nombre real de la tabla.
    protected $primaryKey = 'id_lote'; // Clave primaria de cada lote.
    public $timestamps = false; // La tabla no usa created_at/updated_at estándar.
    protected $guarded = []; // Permite asignar cualquier campo; usar solo desde código interno confiable.

    // Convierte cantidad a entero al leer/escribir el atributo en Eloquent.
    protected $casts = ['cantidad' => 'integer'];
}
