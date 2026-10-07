<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    protected $table = 'DETALLE_VENTA';
    protected $primaryKey = 'id_detalle_venta';

    // Relaciones hacia arriba (Pertenece a...)
    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta', 'id_venta');
    }

    public function promocion()
    {
        return $this->belongsTo(Promocion::class, 'id_promocion', 'id_promocion');
    }
}
