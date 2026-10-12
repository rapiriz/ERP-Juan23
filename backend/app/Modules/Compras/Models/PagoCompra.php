<?php
declare(strict_types=1);

namespace App\Modules\Compras\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoCompra extends Model
{
    protected $table = 'PAGO_COMPRA';
    protected $primaryKey = 'id_pago_compra';
    public $timestamps = false;

    protected $fillable = [
        'id_compra',
        'metodo',
        'importe',
        'fecha_pago',
        'referencia',
        'id_usuario',
    ];

    protected $casts = [
        'id_pago_compra' => 'integer',
        'id_compra' => 'integer',
        'importe' => 'float',
        'fecha_pago' => 'date:Y-m-d',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'id_compra', 'id_compra');
    }
}
