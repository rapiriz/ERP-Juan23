<?php

namespace App\Modules\Stock\Services;

use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Models\Lote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LotService
{
    /** La recepción mantiene bloqueado el producto y comparte su transacción. */
    public function forReceipt(int $product, string $number, string $expiry): Lote
    {
        validator(['nro_lote' => $number, 'fecha_vencimiento' => $expiry], ['nro_lote' => 'required|string|max:100', 'fecha_vencimiento' => 'required|date_format:Y-m-d'])->validate();
        $lot = Lote::where('id_producto', $product)->where('nro_lote', trim($number))->lockForUpdate()->first();
        if ($lot) {
            if ($lot->estado_trazabilidad !== 'controlado' || $lot->fecha_vencimiento->toDateString() !== $expiry) {
                throw ValidationException::withMessages(['lote' => 'El lote ya existe con otra fecha o está pendiente de revisión.']);
            }
            return $lot;
        }
        return Lote::create(['id_producto' => $product, 'nro_lote' => trim($number), 'fecha_vencimiento' => $expiry,
            'cantidad_inicial' => 0, 'cantidad_actual' => 0, 'estado_trazabilidad' => 'controlado']);
    }

    public function register(array $data, int $actor): Lote
    {
        validator($data, ['id_producto' => 'required|integer|min:1', 'nro_lote' => 'required|string|max:100',
            'fecha_vencimiento' => 'required|date_format:Y-m-d', 'cantidad' => 'required|integer|min:1|max:2147483647',
            'id_unidad' => 'nullable|integer|min:1', 'asociar_ingreso' => 'required|boolean'])->validate();
        return DB::transaction(function () use ($data, $actor) {
            $product = Producto::whereKey($data['id_producto'])->lockForUpdate()->firstOrFail();
            abort_unless($product->estado === 'activo', 422, 'El producto debe estar activo.');
            $quantity = ProductUnitService::quantity((int) $data['cantidad'], (int) $product->id_producto, $data['id_unidad'] ?? null);
            abort_if(Lote::where('id_producto', $product->id_producto)->where('nro_lote', trim($data['nro_lote']))->exists(), 409, 'El número de lote ya existe para este producto.');
            $ingress = filter_var($data['asociar_ingreso'], FILTER_VALIDATE_BOOLEAN);
            if (! $ingress) {
                $tracked = Lote::where('id_producto', $product->id_producto)->where('estado_trazabilidad', 'controlado')->sum('cantidad_actual');
                if ($quantity > (int) $product->stock - (int) $tracked) {
                    throw ValidationException::withMessages(['cantidad' => 'La cantidad supera el stock existente todavía no asignado a lotes.']);
                }
            }
            $lot = Lote::create(['id_producto' => $product->id_producto, 'nro_lote' => trim($data['nro_lote']),
                'fecha_vencimiento' => $data['fecha_vencimiento'], 'cantidad_inicial' => $ingress ? 0 : $quantity,
                'cantidad_actual' => $ingress ? 0 : $quantity, 'estado_trazabilidad' => 'controlado']);
            if ($ingress) {
                app(StockService::class)->registrarMovimiento($product, 'ingreso', (int) $data['cantidad'], 'Ingreso lote '.$lot->nro_lote,
                    $actor, $data['id_unidad'] ?? null, idLote: $lot->id_lote);
            } else {
                DB::table('stock_lote_movimientos')->insert(['id_lote' => $lot->id_lote, 'id_movimiento' => null,
                    'id_producto' => $product->id_producto, 'id_venta' => null, 'delta' => $quantity, 'usuario_id' => $actor,
                    'motivo' => 'Asignación a lote de stock ya existente; no suma inventario', 'created_at' => now()]);
            }
            return $lot->refresh();
        }, 3);
    }

    public function adopt(int $id, int $actor): Lote
    {
        $candidate = Lote::findOrFail($id);
        return DB::transaction(function () use ($candidate, $id, $actor) {
            $product = Producto::whereKey($candidate->id_producto)->lockForUpdate()->firstOrFail();
            $lot = Lote::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($lot->estado_trazabilidad === 'controlado') {
                return $lot;
            }
            $tracked = Lote::where('id_producto', $product->id_producto)->where('estado_trazabilidad', 'controlado')->sum('cantidad_actual');
            abort_if((float) $lot->getRawOriginal('cantidad_actual') !== (float) $lot->cantidad_actual
                || (float) $lot->getRawOriginal('cantidad_inicial') !== (float) $lot->cantidad_inicial
                || $lot->cantidad_actual < 0 || $lot->cantidad_actual > $lot->cantidad_inicial
                || (int) $tracked + $lot->cantidad_actual > (int) $product->stock, 409,
                'El lote histórico no coincide con el inventario. Revisá sus cantidades antes de adoptarlo.');
            $lot->update(['estado_trazabilidad' => 'controlado']);
            DB::table('stock_lote_movimientos')->insert(['id_lote' => $id, 'id_movimiento' => null,
                'id_producto' => $lot->id_producto, 'id_venta' => null, 'delta' => $lot->cantidad_actual,
                'usuario_id' => $actor, 'motivo' => 'Adopción explícita de lote histórico verificado; sin alterar stock', 'created_at' => now()]);
            return $lot;
        }, 3);
    }
}
