<?php

namespace App\Modules\Compras\Services;

use App\Modules\Compras\Models\Compra;
use App\Modules\PedidosDeCompra\Models\OrdenCompra;
use App\Modules\PedidosDeCompra\Services\PurchaseOrderProgress;
use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Services\ProductUnitService;
use App\Modules\Stock\Services\StockService;
use Illuminate\Support\Facades\DB;

/** El cierre físico no acredita pagos; las anulaciones conservan sus documentos. */
class PurchaseLifecycle
{
    public function closePartial(int $id, int $actor): Compra
    {
        return DB::transaction(function () use ($id, $actor) {
            $orderId = Compra::whereKey($id)->value('id_orden');
            $order = $orderId ? OrdenCompra::whereKey($orderId)->lockForUpdate()->firstOrFail() : null;
            $purchase = Compra::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($purchase->estado, ['pendiente', 'parcialmente_recibida'], true), 409, 'La compra ya está cerrada o anulada.');
            $details = $purchase->detalles()->orderBy('id_detalle_compra')->lockForUpdate()->get();
            $value = $details->sum(fn ($d) => (int) $d->cantidad_recibida * (int) round($d->precio_unitario * 100));
            abort_unless($details->sum('cantidad_recibida') > 0, 422, 'No puede completarse una compra sin recepción física.');
            abort_if((int) round($purchase->totalPagado() * 100) > $value, 422, 'El pago supera el valor efectivamente recibido; regularice el pago antes de cerrar.');
            foreach ($details as $detail) {
                $detail->update(['cantidad' => $detail->cantidad_recibida,
                    'subtotal' => ((int) $detail->cantidad_recibida * (int) round($detail->precio_unitario * 100)) / 100]);
            }
            $purchase->update(['importe_total' => $value / 100, 'saldo_pendiente' => 0, 'estado' => 'completada',
                'cierre_parcial' => true, 'observaciones' => trim(($purchase->observaciones ?? '')."\nCierre parcial definitivo: cantidades no recibidas canceladas, sin acreditar pagos."),
                'fecha_modificacion' => now(), 'id_usuario_modificacion' => $actor]);
            $this->audit($purchase, $actor, 'cierre_parcial');
            if ($order) {
                if (! $order->compras()->where('estado', '!=', 'cancelada')->where('estado', '!=', 'completada')->exists()) {
                    $order->update(['cierre_parcial' => true]);
                }
                app(PurchaseOrderProgress::class)->sync($order);
            }

            return $purchase;
        }, 3);
    }

    public function cancel(int $id, int $actor): Compra
    {
        return DB::transaction(function () use ($id, $actor) {
            $orderId = Compra::whereKey($id)->value('id_orden');
            $order = $orderId ? OrdenCompra::whereKey($orderId)->lockForUpdate()->firstOrFail() : null;
            $purchase = Compra::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($purchase->estado === 'cancelada', 409, 'La compra ya está anulada.');
            abort_if((int) round($purchase->totalPagado() * 100) > 0, 422, 'La compra tiene pagos; requiere reversión financiera documentada antes de anular.');
            $details = $purchase->detalles()->orderBy('id_producto')->lockForUpdate()->get();
            Producto::whereIn('id_producto', $details->pluck('id_producto'))->orderBy('id_producto')->lockForUpdate()->get();
            $receiptIds = $purchase->recepciones()->pluck('id_recepcion');
            $quantities = [];
            foreach ($details as $detail) {
                if ((int) $detail->cantidad_recibida <= 0) continue;
                $quantities[$detail->id_producto] = ($quantities[$detail->id_producto] ?? 0)
                    + ProductUnitService::quantity((int) $detail->cantidad_recibida, (int) $detail->id_producto, $detail->id_unidad);
            }
            foreach ($quantities as $productId => $base) {
                if ($base === 0) continue;
                $ingress = (int) DB::table('movimiento_stock')->whereIn('id_recepcion', $receiptIds)
                    ->where('id_producto', $productId)->where('tipo', 'ingreso')->sum('cantidad_base');
                abort_if($ingress < $base, 409, 'La compra histórica carece de ingreso verificable; revisar antes de revertir.');
                $lots = DB::table('stock_lote_movimientos as l')->join('movimiento_stock as m', 'm.id_movimiento', '=', 'l.id_movimiento')
                    ->whereIn('m.id_recepcion', $receiptIds)->where('l.id_producto', $productId)->where('l.delta', '>', 0)
                    ->selectRaw('l.id_lote, SUM(l.delta) as cantidad')->groupBy('l.id_lote')->get();
                $tracked = (int) $lots->sum('cantidad');
                abort_if($tracked > $base, 409, 'Las cantidades recibidas y sus lotes no coinciden.');
                $untracked = $base - $tracked;
                abort_if($untracked > 0 && DB::table('lote')->where('id_producto', $productId)->where('estado_trazabilidad', 'controlado')->where('cantidad_actual', '>', 0)->exists(),
                    409, 'Hay stock mezclado con lotes sin referencia de recepción; requiere revisión antes de revertir.');
                try {
                    foreach ($lots as $lot) {
                        app(StockService::class)->registrarMovimiento(Producto::findOrFail($productId), 'ajuste', (int) $lot->cantidad,
                            'Anulación de compra #'.$id, $actor, idLote: (int) $lot->id_lote);
                    }
                    if ($untracked > 0) {
                        app(StockService::class)->registrarMovimiento(Producto::findOrFail($productId), 'ajuste', $untracked,
                            'Anulación de compra #'.$id, $actor);
                    }
                } catch (\RuntimeException $e) {
                    abort(422, $e->getMessage());
                }
            }
            $purchase->update(['estado' => 'cancelada', 'fecha_cancelacion' => today(), 'id_usuario_modificacion' => $actor]);
            $this->audit($purchase, $actor, 'anulacion');
            if ($order) {
                $order->update(['cierre_parcial' => false]);
                app(PurchaseOrderProgress::class)->sync($order);
            }

            return $purchase;
        }, 3);
    }

    private function audit(Compra $purchase, int $actor, string $action): void
    {
        DB::table('log_auditoria')->insert(['tabla_afectada' => 'compra', 'id_registro' => $purchase->id_compra,
            'accion' => 'UPDATE', 'valores_anteriores' => null,
            'valores_nuevos' => json_encode(['operacion' => $action, 'estado' => $purchase->estado, 'importe_total' => $purchase->importe_total], JSON_THROW_ON_ERROR),
            'id_usuario' => $actor, 'fecha_hora' => now()]);
    }
}
