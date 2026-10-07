<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    // Solo para que no falle la prueba. El otro equipo luego lo completará.
    protected $table = 'PRODUCTO';
    protected $primaryKey = 'id_producto';
}
