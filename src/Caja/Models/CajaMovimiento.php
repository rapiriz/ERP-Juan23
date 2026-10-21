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
    public const TIPO_PAGO_PROVEEDOR = 'pago_proveedor';
    public const TIPO_IMPUESTO = 'impuesto';

    public const TIPOS_VALIDOS = [
        self::TIPO_COBRO,
        self::TIPO_INGRESO_MANUAL,
        self::TIPO_EGRESO,
        self::TIPO_EXTRACCION,
        self::TIPO_PAGO_PROVEEDOR,
        self::TIPO_IMPUESTO,
    ];

    /** Tipos que puede registrar un repartidor (cobros y gastos del reparto). */
    public const TIPOS_REPARTIDOR = [
        self::TIPO_COBRO,
        self::TIPO_EGRESO,
    ];

    protected $fillable = [
        'id_caja',
        'id_cobro',
        'fecha',
        'fecha_hora_carga',
        'monto',
        'tipo',
        'concepto',
        'id_usuario',
        'monto_anterior',
        'id_usuario_modificacion',
        'fecha_hora_modificacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_hora_carga' => 'datetime',
        'fecha_hora_modificacion' => 'datetime',
        'monto' => 'decimal:2',
        'monto_anterior' => 'decimal:2',
    ];

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'id_caja', 'id_caja');
    }

    public function esIngreso(): bool
    {
        return in_array($this->tipo, [self::TIPO_COBRO, self::TIPO_INGRESO_MANUAL], true);
    }

    /**
     * Admin: todos los tipos. Repartidor: cobro y egreso.
     * Cualquier otro rol (ej. vendedor) no maneja caja: lista vacía.
     */
    public static function tiposPermitidosPara(string $rol): array
    {
        return match ($rol) {
            Caja::ROL_REPARTIDOR => self::TIPOS_REPARTIDOR,
            Caja::ROL_ADMIN => self::TIPOS_VALIDOS,
            default => [],
        };
    }
}