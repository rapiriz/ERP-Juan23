<?php

namespace App\Cobros\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent: CobroVenta (Tabla intermedia COBRO_VENTA)
 */
class CobroVenta extends Model
{
    protected $table = 'COBRO_VENTA';
    public $timestamps = false;
    public $incrementing = false; // Clave primaria compuesta

    protected $fillable = [
        'id_cobro',
        'id_venta',
        'monto_aplicado',
    ];

    protected $casts = [
        'id_cobro' => 'integer',
        'id_venta' => 'integer',
        'monto_aplicado' => 'float',
    ];

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class, 'id_cobro', 'id_cobro');
    }
}
