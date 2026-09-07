<?php

namespace App\Caja\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model MÍNIMO — el diseño completo de este módulo le corresponde a quien
 * tenga asignada la HU de Caja. Se creó acá solo con los campos que
 * Conciliación Bancaria necesita referenciar (ver 02-arquitectura-tecnica.md).
 */
class CajaMovimiento extends Model
{
    protected $table = 'CAJA_MOVIMIENTO';
    protected $primaryKey = 'id_movimiento_caja';
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'monto',
        'tipo',
        'concepto',
        'id_usuario',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];
}