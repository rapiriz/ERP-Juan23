<?php

namespace App\Modules\CuentaCorriente\Services;

use Illuminate\Support\Facades\DB;

/** Un asiento histórico no demuestra, por sí solo, un crédito autorizado. */
class CreditNoteEvidence
{
    public function verified(object $entry): bool
    {
        if ($entry->tipo !== 'nota_credito' || (int) round($entry->importe * 100) <= 0) {
            return false;
        }

        return DB::table('g4_notas_credito')->where('movimiento_id', $entry->id)
            ->where('cliente_id', $entry->cliente_id)->where('total', $entry->importe)
            ->when($entry->venta_id !== null, fn ($q) => $q->where('venta_id', $entry->venta_id))->exists();
    }
}
