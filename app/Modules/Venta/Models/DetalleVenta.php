<?php

namespace App\Modules\Venta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PASO 1 - MODELO
 * Representa la tabla "detalle_venta": cada fila es un producto dentro de una venta.
 */
class DetalleVenta extends Model
{
    // Nombre real de la tabla.
    protected $table = 'detalle_venta';

    // Clave primaria real.
    protected $primaryKey = 'id_detalle';

    // La tabla no tiene created_at / updated_at.
    public $timestamps = false;

    // Columnas que se pueden cargar de forma masiva (create / createMany).
    protected $fillable = [
        'id_venta',
        'id_producto',
        'id_promocion',
        'cantidad',
        'precio_unitario',
        'descuento',
        'subtotal',
    ];

    // Tipos de dato al leer desde la BD.
    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Relación inversa: cada detalle PERTENECE A una venta.
     * Uso: $detalle->venta
     */
    public function venta(): BelongsTo
    {
        // (Modelo relacionado, FK en detalle_venta, PK en venta)
        return $this->belongsTo(Venta::class, 'id_venta', 'id_venta');
    }
}
