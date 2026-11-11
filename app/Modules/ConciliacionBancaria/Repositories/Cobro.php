<?php

namespace App\Modules\ConciliacionBancaria\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Adaptador / Modelo de Cobro para Conciliación Bancaria.
 * Resuelve cobros bancarios (transferencias/cheques) buscando prioritariamente
 * en RENDICION_COBRO (G2) y ofreciendo fallback a la tabla legacy cobros si existiese.
 */
class Cobro extends Model
{
    protected $table = 'RENDICION_COBRO';
    protected $primaryKey = 'id_rendicion_cobro';
    public $timestamps = false;

    protected $fillable = [
        'id_rendicion',
        'id_cliente',
        'id_factura',
        'monto',
        'medio_pago',
        'fecha_registro',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_registro' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Si la tabla RENDICION_COBRO no existe pero sí cobros, fallback
        if (!Schema::hasTable('RENDICION_COBRO') && Schema::hasTable('cobros')) {
            $this->setTable('cobros');
            $this->primaryKey = 'id';
            $this->timestamps = true;
        }
    }

    /**
     * Accesor para monto unificado
     */
    public function getMontoAttribute()
    {
        return $this->attributes['monto'] ?? ($this->attributes['monto_total'] ?? 0);
    }

    /**
     * Accesor para id_cobro unificado
     */
    public function getIdCobroAttribute()
    {
        return $this->attributes['id_rendicion_cobro'] ?? ($this->attributes['id'] ?? null);
    }

    /**
     * Accesor para fecha_cobro unificada
     */
    public function getFechaCobroAttribute()
    {
        return $this->attributes['fecha_registro'] ?? ($this->attributes['fecha'] ?? null);
    }
}
