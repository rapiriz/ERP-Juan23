<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // ORM de Laravel para consultar el catálogo y el stock.
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Tipo de relación muchos-a-muchos.

/**
 * Modelo Producto  ->  tabla `producto`
 *
 * COLUMNAS CLAVE PARA VENTAS:
 *   - codigo      : código único (lo que se escribe/escanea en el buscador).
 *   - precioMin   : precio LISTA 1 — Minorista.
 *   - precioMay   : precio LISTA 2 — Mayorista.
 *   - stock       : stock ACTUAL. Se descuenta al cobrar (VentaService).
 *   - estado      : solo se vende si es 'activo'.
 *
 * ATENCIÓN: esta tabla NO tiene created_at/updated_at (tiene fecha_alta, etc.),
 * por eso $timestamps = false.
 *
 * USADO POR: CarritoService, PromocionService, VentaService, ProductoController.
 *
 * IMPORTANCIA Y CONEXIONES:
 *   Es la fuente de datos del catálogo y del stock actual. ProductoController
 *   lo usa para buscar; CarritoService recalcula precios, descuentos y stock;
 *   PromocionService comprueba productos incluidos en promociones; VentaService
 *   vuelve a bloquear/verificar el producto y descuenta stock al cobrar.
 *
 * CONEXIÓN CON EL FRONT:
 *   El navegador no accede al modelo ni escribe directamente en producto.
 *   Recibe campos seleccionados por ProductoController o el estado recalculado
 *   por CarritoService. El precio entregado ya corresponde a la lista activa;
 *   al cobrar, el stock puede cambiar y el servidor vuelve a validarlo.
 */
class Producto extends Model // Representa una fila del catálogo en la tabla producto.
{
    protected $table = 'producto'; // Nombre real de la tabla.
    protected $primaryKey = 'id_producto'; // Clave primaria, no la convención id.
    public $timestamps = false; // La tabla usa fecha_alta, no timestamps estándar de Laravel.

    // Normaliza tipos al leer atributos: precios numéricos y stock entero.
    protected $casts = [
        'precioMay' => 'float',
        'precioMin' => 'float',
        'stock'     => 'integer',
    ];

    /**
     * Scope local reutilizable: Producto::activo() agrega estado = activo.
     * Lo emplea el buscador; el cobro además verifica nuevamente el estado.
     */
    public function scopeActivo($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Relación muchos-a-muchos con promociones mediante promocion_producto.
     * Los argumentos indican tabla pivote y claves de producto/promoción.
     */
    public function promociones(): BelongsToMany
    {
        return $this->belongsToMany(Promocion::class, 'promocion_producto', 'id_producto', 'id_promocion');
    }
}
//grupo 3 es productos
