<?php

namespace App\Modules\ConciliacionBancaria\Repositories;

use Illuminate\Database\Eloquent\Model;

/**
 * Adaptador / Modelo de Cobro para Conciliación Bancaria.
 * Mapea exclusivamente a cobro. Los alias JSON no cambian sus claves ni copian registros.
 */
class Cobro extends Model
{
    protected $table = 'cobro';

    protected $primaryKey = 'id_cobro';

    public $timestamps = false;

    protected $appends = ['id', 'cliente_id', 'usuario_id'];

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'fecha',
        'monto_total',
        'medio_pago',
        'comprobante_nro',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'monto_total' => 'decimal:2',
    ];

    public function getIdAttribute()
    {
        return $this->attributes['id_cobro'] ?? null;
    }

    public function getClienteIdAttribute()
    {
        return $this->attributes['id_cliente'] ?? null;
    }

    public function getUsuarioIdAttribute()
    {
        return $this->attributes['id_usuario'] ?? null;
    }

    /**
     * Accesor para compatibilidad con código existente que lee $cobro->monto
     */
    public function getMontoAttribute()
    {
        return $this->attributes['monto_total'] ?? 0;
    }

    /**
     * Accesor para compatibilidad con código existente que lee $cobro->id_cobro
     */
    public function getIdCobroAttribute()
    {
        return $this->attributes['id_cobro'] ?? null;
    }

    /**
     * Accesor para compatibilidad con código existente que lee $cobro->fecha_cobro
     */
    public function getFechaCobroAttribute()
    {
        return $this->attributes['fecha'] ?? null;
    }
}
