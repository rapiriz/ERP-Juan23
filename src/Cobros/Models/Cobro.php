<?php

namespace App\Cobros\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model MÍNIMO — el diseño completo de este módulo le corresponde a quien
 * tenga asignada la HU de Cobros. Se creó acá solo con los campos que
 * Conciliación Bancaria necesita referenciar (ver 02-arquitectura-tecnica.md).
 */
class Cobro extends Model
{
    protected $table = 'COBRO';
    protected $primaryKey = 'id_cobro';
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'fecha_cobro',
        'monto',
        'medio_pago',
        'estado',
    ];

    protected $casts = [
        'fecha_cobro' => 'date',
        'monto' => 'decimal:2',
    ];
}