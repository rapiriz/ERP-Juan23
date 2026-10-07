<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    // 1. Sobreescribir convenciones de Laravel
    protected $table = 'PROMOCION';
    protected $primaryKey = 'id_promocion';

    // Si la tabla no tiene created_at/updated_at, descomenta esta línea:
    // public $timestamps = false;

    // Campos que se pueden llenar masivamente
    protected $fillable = [
        'nombre',
        'tipo_descuento',
        'valor',
        'vigencia_desde',
        'vigencia_hasta',
        'condiciones',
        'estado'
    ];

    // 2. Relación con Producto
    public function productos()
    {
        // Al no usar la convención, hay que pasarle a Laravel exactamente los nombres:
        // (ModeloRelacionado, TablaIntermedia, ClaveDeEsteModelo, ClaveDelOtroModelo)
        return $this->belongsToMany(Producto::class, 'PROMOCION_PRODUCTO', 'id_promocion', 'id_producto');
    }
}
