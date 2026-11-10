<?php

namespace App\Modules\PedidosDeCompra\Services;

use App\Modules\PedidosDeCompra\Models\OrdenCompra;
use App\Modules\Stock\Services\ProductUnitService;
use Illuminate\Support\Facades\DB;

class PurchaseOrderProgress
{
    private function expected(OrdenCompra $order): array
    {
        $expected = [];
        foreach ($order->detalles()->orderBy('id_producto')->lockForUpdate()->get() as $detail) {
            $id = (int) $detail->id_producto;
            $expected[$id] = ($expected[$id] ?? 0) + ProductUnitService::quantity((int) $detail->cantidad_solicitada, $id, $detail->id_unidad);
        }

        return $expected;
    }

    public function requirePurchase(OrdenCompra $order, array $items): void
    {
        $expected = $this->expected($order);
        $committed = [];
        $details = DB::table('detalle_compra as d')->join('compra as c', 'c.id_compra', '=', 'd.id_compra')
            ->where('c.id_orden', $order->id_orden)->where('c.estado', '!=', 'cancelada')->orderBy('d.id_producto')->lockForUpdate()->get(['d.*']);
        foreach ($details as $d) {
            if ((int) $d->cantidad <= 0) continue;
            $committed[$d->id_producto] = ($committed[$d->id_producto] ?? 0) + ProductUnitService::quantity((int) $d->cantidad, (int) $d->id_producto, $d->id_unidad);
        }
        foreach ($items as $item) {
            $id = (int) $item['id_producto'];
            $committed[$id] = ($committed[$id] ?? 0) + ProductUnitService::quantity((int) $item['cantidad'], $id, $item['id_unidad'] ?? null);
            abort_if(! isset($expected[$id]) || $committed[$id] > $expected[$id], 422, 'La compra supera lo solicitado aún no comprometido de la orden o incluye un producto ajeno.');
        }
    }

    public function sync(OrdenCompra $order): string
    {
        $expected = $this->expected($order);
        $received = [];
        foreach (DB::table('detalle_compra as d')->join('compra as c', 'c.id_compra', '=', 'd.id_compra')
            ->where('c.id_orden', $order->id_orden)->where('c.estado', '!=', 'cancelada')->orderBy('d.id_producto')->lockForUpdate()->get(['d.*']) as $d) {
            if ($d->cantidad_recibida > 0) {
                $received[$d->id_producto] = ($received[$d->id_producto] ?? 0)
                    + ProductUnitService::quantity((int) $d->cantidad_recibida, (int) $d->id_producto, $d->id_unidad);
            }
        }
        $complete = $expected && collect($expected)->every(fn ($quantity, $id) => ($received[$id] ?? 0) >= $quantity);
        $complete = $complete || ($order->cierre_parcial && $received && ! $order->compras()->whereNotIn('estado', ['completada', 'cancelada'])->exists());
        $state = $order->estado === 'cancelada' ? 'cancelada' : ($complete ? 'completada' : ($received ? 'recibida_parcialmente' : ($order->fecha_envio ? 'enviada' : 'pendiente')));
        $order->update(['estado' => $state, 'fecha_modificacion' => today()]);

        return $state;
    }
}
