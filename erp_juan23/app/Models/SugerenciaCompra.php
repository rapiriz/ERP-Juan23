<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SugerenciaCompra extends Model
{
    protected $table = 'sugerencias_compra';

    protected $fillable = [
        'producto_id',
        'proveedor_id',
        'cantidad_sugerida',
        'cantidad_minima',
        'cantidad_maxima',
        'precio_unitario',
        'costo_total',
        'velocidad_venta_diaria',
        'plazo_entrega_dias',
        'fecha_reorden',
        'estado',
        'motivo_generacion',
        'observaciones'
    ];

    protected $casts = [
        'cantidad_sugerida' => 'integer',
        'cantidad_minima' => 'integer',
        'cantidad_maxima' => 'integer',
        'precio_unitario' => 'float',
        'costo_total' => 'float',
        'velocidad_venta_diaria' => 'float',
        'plazo_entrega_dias' => 'integer',
        'fecha_reorden' => 'date'
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }
}
