<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoLote extends Model
{
    protected $table = 'producto_lotes';

    protected $fillable = [
        'producto_id',
        'numero_lote',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_actual',
        'ubicacion_almacen',
        'estado',
        'observaciones'
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'cantidad_inicial' => 'integer',
        'cantidad_actual' => 'integer'
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function scopeProximosAVencer($query, int $dias = 30)
    {
        $fechaLimite = now()->addDays($dias);

        return $query
            ->where('fecha_vencimiento', '<=', $fechaLimite)
            ->where('fecha_vencimiento', '>', now())
            ->where('cantidad_actual', '>', 0)
            ->orderBy('fecha_vencimiento', 'asc');
    }

    public function scopeVencidos($query)
    {
        return $query
            ->where('fecha_vencimiento', '<=', now())
            ->where('cantidad_actual', '>', 0);
    }

    public function getDiasRestantesAttribute(): int
    {
        return now()->diffInDays($this->fecha_vencimiento);
    }

    public function getPorcentajeConsumidoAttribute(): float
    {
        if ($this->cantidad_inicial == 0) return 0;
        return round((1 - ($this->cantidad_actual / $this->cantidad_inicial)) * 100, 2);
    }
}
