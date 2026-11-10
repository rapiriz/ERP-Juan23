<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Lote;
use App\Modules\Stock\Models\MovimientoStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Se llama dentro de la transacción y con el producto bloqueado por StockService. */
class LotStockAllocator
{
    public function apply(MovimientoStock $movement, int $delta, int $previousStock, ?int $lotId): void
    {
        if (! Schema::hasTable('stock_lote_movimientos')) {
            return;
        }
        $lots = Lote::where('id_producto', $movement->id_producto)->where('estado_trazabilidad', 'controlado')
            ->orderBy('fecha_vencimiento')->orderBy('id_lote')->lockForUpdate()->get();
        $tracked = (int) $lots->sum('cantidad_actual');
        if ($tracked > $previousStock) {
            throw ValidationException::withMessages(['lote' => 'Los lotes controlados superan el stock físico; revisar trazabilidad antes de operar.']);
        }
        if ($delta > 0 && $lotId !== null) {
            $lot = $lots->firstWhere('id_lote', $lotId);
            if (! $lot) {
                throw ValidationException::withMessages(['lote' => 'El lote debe estar controlado y pertenecer al producto.']);
            }
            $this->change($lot, $movement, $delta, true);
            return;
        }
        if ($delta > 0 && $movement->id_venta) {
            // Anulaciones/devoluciones restauran lotes realmente consumidos por esa venta.
            $consumed = DB::table('stock_lote_movimientos')->where('id_venta', $movement->id_venta)
                ->where('id_producto', $movement->id_producto)->selectRaw('id_lote, SUM(delta) as neto')->groupBy('id_lote')->pluck('neto', 'id_lote');
            foreach ($lots as $lot) {
                $quantity = min($delta, max(0, -(int) $consumed->get($lot->id_lote, 0)));
                if ($quantity > 0) {
                    $this->change($lot, $movement, $quantity);
                    $delta -= $quantity;
                }
                if ($delta === 0) {
                    break;
                }
            }
            return; // El remanente corresponde a unidades históricas no trazadas.
        }
        if ($delta >= 0) {
            return;
        }
        $needed = -$delta;
        $eligible = $movement->tipo === 'venta'
            ? $lots->filter(fn ($lot) => $lot->fecha_vencimiento->toDateString() >= today()->toDateString()) : $lots;
        if ($lotId !== null) {
            $eligible = $eligible->where('id_lote', $lotId);
        }
        $untracked = $lotId === null ? max(0, $previousStock - $tracked) : 0;
        if ((int) $eligible->sum('cantidad_actual') + $untracked < $needed) {
            throw ValidationException::withMessages(['lote' => 'No hay unidades aptas suficientes: existen lotes vencidos o la cantidad supera el lote elegido.']);
        }
        foreach ($eligible as $lot) {
            $quantity = min($needed, max(0, (int) $lot->cantidad_actual));
            if ($quantity > 0) {
                $this->change($lot, $movement, -$quantity);
                $needed -= $quantity;
            }
            if ($needed === 0) {
                break;
            }
        }
    }

    private function change(Lote $lot, MovimientoStock $movement, int $delta, bool $newReceipt = false): void
    {
        $lot->cantidad_actual += $delta;
        if ($newReceipt) {
            $lot->cantidad_inicial += $delta;
        }
        $lot->save();
        DB::table('stock_lote_movimientos')->insert(['id_lote' => $lot->id_lote, 'id_movimiento' => $movement->id_movimiento,
            'id_producto' => $movement->id_producto, 'id_venta' => $movement->id_venta, 'delta' => $delta,
            'usuario_id' => $movement->id_usuario, 'motivo' => mb_substr($movement->motivo, 0, 255), 'created_at' => now()]);
    }
}
