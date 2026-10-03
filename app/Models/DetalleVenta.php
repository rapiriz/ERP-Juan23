<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // ORM de Laravel para representar filas de la base.

/**
 * Modelo DetalleVenta  ->  tabla `detalle_venta`
 *
 * Una línea de la venta (producto, cantidad, precio, descuento $, subtotal).
 * descuento = MONTO en pesos descontado en la línea (no porcentaje).
 * id_promocion = promo que originó el descuento (NULL si fue manual o no hubo).
 * USADO POR: VentaService.
 *
 * $guarded = [] : permite create([...]) con cualquier columna. Es seguro
 * porque este modelo SOLO se escribe desde los Services (con datos ya
 * validados), jamás directamente con lo que llega del request.
 *
 * IMPORTANCIA Y CONEXIONES:
 *   Cada registro conserva una línea de la venta al momento de cobrar. Así,
 *   el historial mantiene el precio y descuento aplicados aunque luego cambien
 *   los datos actuales del producto o la promoción.
 *   VentaService crea estas filas dentro de la misma transacción que la cabecera
 *   Venta. Venta::detalles() permite recuperar las líneas desde esa venta.
 *
 * CONEXIÓN CON EL FRONT:
 *   El navegador no crea DetalleVenta ni envía estos importes como fuente de verdad.
 *   Envía la solicitud de cobro; VentaService toma el carrito recalculado y guarda
 *   sus valores. La pantalla trabaja antes del cobro con las líneas de CarritoService.
 *
 * CUIDADO:
 *   $guarded = [] habilita asignación masiva de cualquier atributo. No usar con
 *   datos crudos del Request; el servicio arma explícitamente los campos guardados.
 */
class DetalleVenta extends Model // Representa un renglón de la tabla detalle_venta.
{
    protected $table = 'detalle_venta'; // Tabla donde se persisten las líneas vendidas.
    protected $primaryKey = 'id_detalle_venta'; // Clave primaria propia de cada renglón.
    public $timestamps = false; // La tabla no usa created_at/updated_at de Laravel.
    protected $guarded = []; // Permite todos los campos; escribir solo desde código interno confiable.
}
