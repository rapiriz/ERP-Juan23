<?php

namespace App\Caja\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reemplaza el stub mínimo original. Se mantienen sin cambios:
 *  - Nombre de tabla: CAJA_MOVIMIENTO
 *  - Nombre de PK: id_movimiento_caja
 * porque ConciliacionDetalle::cajaMovimiento() ya apunta a ese nombre exacto.
 * No tocar esos dos nombres sin avisar a Conciliación Bancaria.
 */
class CajaMovimiento extends Model
{
    protected $table = 'CAJA_MOVIMIENTO';
    protected $primaryKey = 'id_movimiento_caja';
    public $timestamps = false;

    public const TIPO_COBRO = 'cobro';
    public const TIPO_INGRESO_MANUAL = 'ingreso_manual';
    public const TIPO_EGRESO = 'egreso';
    public const TIPO_EXTRACCION = 'extraccion';

    public const TIPOS_VALIDOS = [
        self::TIPO_COBRO,
        self::TIPO_INGRESO_MANUAL,
        self::TIPO_EGRESO,
        self::TIPO_EXTRACCION,
    ];

    protected $fillable = [
        'id_caja',
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

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'id_caja', 'id_caja');
    }

    public function esIngreso(): bool
    {
        return in_array($this->tipo, [self::TIPO_COBRO, self::TIPO_INGRESO_MANUAL], true);
    }
}