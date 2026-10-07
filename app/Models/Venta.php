<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'VENTA';
    protected $primaryKey = 'id_venta';

    // Una Venta tiene muchos Detalles de Venta
    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }
}
