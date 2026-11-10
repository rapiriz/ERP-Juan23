<?php

namespace App\Modules\NotificacionesTransferencia\Models;

use App\Modules\Clientes\Models\Cliente;
use Illuminate\Database\Eloquent\Model;

class TransferNotification extends Model
{
    protected $table = 'g1_transferencias';

    protected $guarded = ['id'];

    protected $casts = ['monto' => 'decimal:2', 'fecha' => 'date'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
