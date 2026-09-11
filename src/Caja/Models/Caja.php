<?php

namespace App\Caja\Models;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'CAJA';
    protected $primaryKey = 'id_caja';
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d H:i:s';

    public const ESTADO_ABIERTA = 'abierta';
    public const ESTADO_CERRADA = 'cerrada';

    protected $fillable = [
        'id_usuario',
        'fecha',
        'monto_inicial',
        'monto_final',
        'diferencia',
        'estado',
        'id_usuario_cierre',
        'fecha_hora_apertura',
        'fecha_hora_cierre',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_inicial' => 'decimal:2',
        'monto_final' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'fecha_hora_apertura' => 'datetime',
        'fecha_hora_cierre' => 'datetime',
    ];

    public function movimientos()
    {
        return $this->hasMany(CajaMovimiento::class, 'id_caja', 'id_caja');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ESTADO_ABIERTA;
    }

    public function perteneceA(int $idUsuario): bool
    {
        return (int) $this->id_usuario === $idUsuario;
    }
}
