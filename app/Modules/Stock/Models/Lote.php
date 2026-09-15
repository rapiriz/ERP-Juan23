<?php
declare(strict_types=1);

namespace App\Modules\Stock\Models;

use App\Modules\Productos\Models\Producto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lote extends Model
{
    protected $table = 'LOTE';
    protected $primaryKey = 'id_lote';
    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_movimiento',
        'id_unidad',
        'nro_lote',
        'cantidad',
        'fecha_vencimiento',
        'estado',
    ];

    protected $casts = [
        'id_lote' => 'integer',
        'id_producto' => 'integer',
        'id_movimiento' => 'integer',
        'id_unidad' => 'integer',
        'cantidad' => 'integer',
        'fecha_vencimiento' => 'date:Y-m-d',
    ];

    protected $appends = [
        'dias_para_vencer',
    ];

    public const ESTADOS = ['vigente', 'vencido', 'consumido'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoStock::class, 'id_movimiento', 'id_movimiento');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'id_unidad', 'id_unidad');
    }

    /**
     * Días que restan para el vencimiento (negativo si ya venció).
     */
    public function getDiasParaVencerAttribute(): ?int
    {
        if (!$this->fecha_vencimiento) {
            return null;
        }
        return (int) Carbon::today()->diffInDays($this->fecha_vencimiento, false);
    }

    /**
     * Scope para filtrar por producto.
     */
    public function scopePorProducto(Builder $query, int $idProducto): Builder
    {
        return $query->where('id_producto', $idProducto);
    }

    /**
     * Scope para lotes vigentes.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', 'vigente')
                     ->where('fecha_vencimiento', '>=', Carbon::today()->toDateString());
    }

    /**
     * Scope para lotes próximos a vencer dentro de un margen de días.
     */
    public function scopePorVencer(Builder $query, int $dias = 30): Builder
    {
        $hoy = Carbon::today()->toDateString();
        $limite = Carbon::today()->addDays($dias)->toDateString();

        return $query->where('estado', 'vigente')
                     ->whereBetween('fecha_vencimiento', [$hoy, $limite]);
    }

    /**
     * Scope para búsqueda flexible por número de lote o código de producto.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term && trim($term) !== '') {
            $term = trim($term);
            return $query->where(function (Builder $q) use ($term) {
                $q->where('nro_lote', 'LIKE', "%{$term}%")
                  ->orWhereHas('producto', function (Builder $qp) use ($term) {
                      $qp->where('codigo', 'LIKE', "%{$term}%")
                         ->orWhere('descripcion', 'LIKE', "%{$term}%");
                  });
            });
        }
        return $query;
    }
}
