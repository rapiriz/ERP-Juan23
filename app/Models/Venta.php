<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'venta';
    protected $primaryKey = 'id_venta';
    protected $guarded = [];
    public $timestamps = false; // ¡Muy importante! Tu tabla no tiene created_at / updated_at[cite: 14]

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'id_venta', 'id_venta');
    }
}
