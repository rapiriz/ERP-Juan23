<?php

namespace App\ConciliacionBancaria\Models;

use App\Cobros\Models\Cobro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cheque extends Model
{
    protected $table = 'CHEQUE';
    protected $primaryKey = 'id_cheque';
    public $timestamps = false;

    protected $fillable = [
        'id_cobro',
        'numero_cheque',
        'banco_emisor',
        'fecha_cobro',
        'estado',
        'fecha_deposito',
        'fecha_rechazo',
    ];

    protected $casts = [
        'fecha_cobro' => 'date',
        'fecha_deposito' => 'date',
        'fecha_rechazo' => 'date',
    ];

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class, 'id_cobro', 'id_cobro');
    }

    public function conciliacionDetalle(): HasOne
    {
        return $this->hasOne(ConciliacionDetalle::class, 'id_cheque', 'id_cheque');
    }
}