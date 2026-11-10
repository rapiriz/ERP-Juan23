<?php

namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    protected $table = 'lote';
    protected $primaryKey = 'id_lote';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['id_lote' => 'integer', 'id_producto' => 'integer', 'cantidad_inicial' => 'integer',
        'cantidad_actual' => 'integer', 'fecha_vencimiento' => 'date:Y-m-d'];
}
