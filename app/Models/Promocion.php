<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // ORM de Laravel para consultar promociones.
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Tipo de relación muchos-a-muchos.

/**
 * Modelo Promocion  ->  tabla `promocion`
 *
 * ¡OJO CON LA COLUMNA `total`!
 *   En esta tabla `total` NO es un importe: según el script SQL, se usa como
 *   PORCENTAJE de descuento (5.00 = 5%, 10.00 = 10%). Para no confundirse,
 *   usar siempre el accessor ->porcentaje.
 *
 * Una promo se considera VIGENTE si: estado = true Y hoy está entre
 * vigencia_desde y vigencia_hasta (si alguna fecha es NULL, no limita).
 *
 * USADO POR: PromocionService, PromocionController, CarritoService.
 *
 * IMPORTANCIA Y CONEXIONES:
 *   Proporciona el filtro de vigencia, el porcentaje normalizado y la relación
 *   con los productos promocionados. PromocionController lo usa para preparar
 *   la vista de promociones; PromocionService y CarritoService determinan si
 *   las condiciones extra (config/ventas.php) se cumplen en el carrito.
 *
 * FRONT:
 *   El navegador no consulta ni modifica este modelo directamente. Recibe la
 *   respuesta de GET /ventas/promociones/activas. Esa lista es informativa:
 *   el descuento final se confirma con el estado devuelto por el carrito,
 *   porque las reglas pueden depender de lista y cantidad.
 */
class Promocion extends Model // Representa una fila de la tabla promocion.
{
    protected $table = 'promocion'; // Nombre real de la tabla.
    protected $primaryKey = 'id_promocion'; // Clave primaria del registro.
    public $timestamps = false; // La tabla no usa created_at/updated_at convencionales.

    // Interpreta el campo estado como booleano al leerlo desde Eloquent.
    protected $casts = [
        'estado' => 'boolean',
    ];

    /**
     * Accessor: $promocion->porcentaje traduce la columna total a porcentaje.
     * Limita el valor expuesto al intervalo 0–100; no modifica el valor guardado.
     */
    public function getPorcentajeAttribute(): float
    {
        return max(0.0, min(100.0, (float) $this->total));
    }

    /**
     * Scope local: Promocion::vigente() filtra activas dentro de sus fechas.
     * Los límites son inclusivos y una fecha NULL significa que no limita ese extremo.
     */
    public function scopeVigente($query)
    {
        // La fecha actual se calcula con la zona horaria configurada en Laravel.
        $hoy = now()->toDateString();

        return $query->where('estado', true)
            ->where(fn ($q) => $q->whereNull('vigencia_desde')->orWhere('vigencia_desde', '<=', $hoy))
            ->where(fn ($q) => $q->whereNull('vigencia_hasta')->orWhere('vigencia_hasta', '>=', $hoy));
    }

    /**
     * Relación muchos-a-muchos con productos por la tabla pivote promocion_producto.
     * Los argumentos indican la tabla pivote y las claves de promoción/producto.
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'promocion_producto', 'id_promocion', 'id_producto');
    }
}
