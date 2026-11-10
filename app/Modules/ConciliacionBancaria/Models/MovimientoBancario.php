<?php

namespace App\Modules\ConciliacionBancaria\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class MovimientoBancario extends Model
{
    protected $table = 'MOVIMIENTO_BANCARIO';

    protected $primaryKey = 'id_movimiento_bancario';

    protected $fillable = [
        'id_periodo', 'fecha_movimiento', 'descripcion', 'tipo', 'estado', 'id_usuario', 'source_fingerprint',
        'id_archivo_importado_log',
        'fecha_operacion',
        'monto',
        'signo_monto',
        'descripcion_original',
        'descripcion_normalizada',
        'nro_comprobante_extraido',
        'cuit_extraido',
        'tipo_movimiento',
        'estado_conciliacion',
    ];

    protected $casts = [
        'fecha_operacion' => 'date',
        'monto' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $movement): void {
            if (! Schema::hasColumns($movement->getTable(), ['fecha_movimiento', 'fecha_operacion'])) {
                return;
            }
            foreach (['fecha_operacion' => 'fecha_movimiento', 'descripcion_original' => 'descripcion'] as $current => $legacy) {
                if ($movement->isDirty($current)) {
                    $movement->$legacy = $current === 'descripcion_original'
                        ? mb_substr((string) $movement->$current, 0, 255) : $movement->$current;
                } elseif ($movement->isDirty($legacy)) {
                    $movement->$current = $movement->$legacy;
                }
            }
            if ($movement->isDirty('signo_monto')) {
                $movement->tipo = $movement->signo_monto === 'egreso' ? 'debito' : 'credito';
            }
            if ($movement->isDirty('estado_conciliacion')) {
                $movement->estado = $movement->estado_conciliacion === 'matcheado' ? 'conciliado' : $movement->estado_conciliacion;
            } elseif ($movement->isDirty('estado')) {
                $movement->estado_conciliacion = $movement->estado === 'conciliado' ? 'matcheado' : $movement->estado;
            }
            if (! $movement->exists) {
                $movement->fecha_importacion = now();
                $movement->id_usuario ??= $movement->archivoImportado?->id_usuario;
            }
        });
    }

    public function archivoImportado(): BelongsTo
    {
        return $this->belongsTo(ArchivoImportadoLog::class, 'id_archivo_importado_log', 'id_archivo_importado_log');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoConciliacion::class, 'id_periodo', 'id_periodo');
    }
}
