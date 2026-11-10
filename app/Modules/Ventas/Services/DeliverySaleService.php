<?php

namespace App\Modules\Ventas\Services;

use App\Modules\Ventas\Models\Venta;
use App\Modules\Ventas\Models\VentaHistorial;
use Illuminate\Support\Facades\DB;

/** Transición acotada para reservar/liberar pedidos de una entrega en preparación. */
class DeliverySaleService
{
    public function change(int $id, string $action, int $actor): void
    {
        DB::transaction(function () use ($id, $action, $actor) {
            $sale = Venta::lockForUpdate()->findOrFail($id);
            abort_if(DB::table('g4_stock_operations')->where('venta_id', $id)->where('estado', 'pendiente')->exists(), 409, 'La venta tiene stock pendiente de sincronizar.');
            [$from,$to] = match ($action) {
                'reservar' => ['confirmada', 'preparada'],'liberar' => ['preparada', 'confirmada'],
                'entregar' => ['preparada', 'entregada'], default => abort(422, 'Acción logística desconocida.')
            };
            abort_unless($sale->estado === $from, 409, 'El pedido cambió de estado y no admite esta operación logística.');
            $sale->estado = $to;
            $sale->save();
            VentaHistorial::create(['id_venta' => $id, 'accion' => 'cambio_estado', 'estado_anterior' => $from, 'estado_nuevo' => $to,
                'motivo' => 'Entrega: '.$action, 'id_usuario' => $actor]);
        });
    }
}
