<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // ORM de Laravel para persistir movimientos de inventario.

/**
 * Modelo MovimientoStock  ->  tabla `movimiento_stock`
 *
 * Historial de stock. tipo='venta' al vender. cantidad SIEMPRE positiva;
 * el signo lo da el tipo (ver comentario en el SQL).
 * USADO POR: VentaService.
 *
 * $guarded = [] : permite create([...]) con cualquier columna. Es seguro
 * porque este modelo SOLO se escribe desde los Services (con datos ya
 * validados), jamás directamente con lo que llega del request.
 *
 * IMPORTANCIA Y CONEXIONES:
 *   Es la bitácora de cambios de inventario, no el stock actual. Al cobrar,
 *   VentaService descuenta Producto.stock y crea aquí el movimiento asociado
 *   al producto, usuario y venta, dentro de la misma transacción.
 *   La cantidad se guarda positiva; el tipo (por ejemplo, 'venta') indica el
 *   sentido del movimiento según las convenciones del esquema.
 *
 * FRONT:
 *   El POS no crea ni envía estos registros. No hay un endpoint de movimientos
 *   en routes/ventas.php; al cobrar, el backend los registra automáticamente.
 *   Para mostrar un historial haría falta un endpoint de lectura del módulo de
 *   inventario y definir allí filtros y permisos.
 *
 * CUIDADO:
 *   $guarded = [] habilita asignación masiva de cualquier columna. No pasarle
 *   datos crudos del Request; construir los campos desde código confiable.
 */
class MovimientoStock extends Model // Una fila de la tabla movimiento_stock.
{
    protected $table = 'movimiento_stock'; // Tabla física del historial de stock.
    protected $primaryKey = 'id_movimiento'; // Identificador único del movimiento.
    public $timestamps = false; // La tabla no usa timestamps convencionales de Laravel.
    protected $guarded = []; // Sin atributos protegidos: usar solo desde servicios internos.
}
