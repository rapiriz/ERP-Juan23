<?php

namespace App\Modules\Ventas\Services;

use App\Modules\Login\Models\User;
use App\Modules\Ventas\Models\Venta;
use App\Modules\Ventas\Models\VentaHistorial;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

/** Recepción física y crédito interno contra el precio neto de la venta original. */
class CommercialReturnService
{
    public function register(int $saleId, array $data, User $actor): array
    {
        abort_unless($actor->rol->value === 'administrativo' && $actor->estado->value === 'activo', 403);
        validator($data, ['request_key' => 'required|uuid', 'motivo' => 'required|string|min:3|max:2000',
            'items' => 'required|array|min:1|max:100', 'items.*.detalle_venta_id' => 'required|integer|min:1|distinct',
            'items.*.cantidad' => 'required|integer|min:1|max:2147483647',
            'items.*.condicion' => 'required|in:apto,danado,vencido'])->validate();
        $items = collect($data['items'])->sortBy('detalle_venta_id')->map(fn ($i) => [
            'detalle_venta_id' => (int) $i['detalle_venta_id'], 'cantidad' => (int) $i['cantidad'], 'condicion' => $i['condicion']])->values()->all();
        $hash = hash('sha256', json_encode([$saleId, trim($data['motivo']), $items], JSON_THROW_ON_ERROR));
        $client = Venta::whereKey($saleId)->value('id_cliente');
        $result = DB::transaction(function () use ($saleId, $data, $actor, $items, $hash, $client) {
            abort_unless($client, 404);
            DB::table('clientes')->where('id', $client)->lockForUpdate()->firstOrFail();
            $sale = Venta::whereKey($saleId)->lockForUpdate()->firstOrFail();
            $existing = DB::table('g4_devoluciones')->where('request_key', $data['request_key'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->payload_hash === $hash && (int) $existing->usuario_id === (int) $actor->id, 409, 'La referencia pertenece a otra devolución.');

                return ['devolucion_id' => (int) $existing->id, 'repetido' => true];
            }
            abort_if(in_array($sale->estado, ['pendiente', 'cancelada', 'anulada'], true), 409, 'La venta no está confirmada o está anulada.');
            abort_if(DB::table('g4_stock_operations')->where('venta_id', $saleId)->where('estado', 'pendiente')->exists(), 409, 'Primero debe sincronizarse el stock pendiente.');
            abort_if(DB::table('entrega_pedido as ep')->join('entrega as e', 'e.id_entrega', '=', 'ep.id_entrega')
                ->where('ep.id_venta', $saleId)->whereNotIn('ep.estado_pedido_entrega', ['entregado', 'no_entregada'])->exists(),
                409, 'El pedido todavía está asignado a una entrega sin cerrar; registrá primero su resultado logístico.');
            $details = $sale->detalles()->orderBy('id_detalle')->lockForUpdate()->get()->keyBy('id_detalle');
            $nets = $this->netAmounts($details, (int) round($sale->total * 100), (int) round($sale->descuento_global * 100));
            $returned = DB::table('g4_devolucion_detalles')->whereIn('detalle_venta_id', $details->keys())->lockForUpdate()->get()
                ->groupBy('detalle_venta_id')->map(fn ($rows) => (int) $rows->sum('cantidad'));
            $total = 0;
            $rows = [];
            $stock = [];
            foreach ($items as $item) {
                $detail = $details->get($item['detalle_venta_id']);
                abort_unless($detail, 422, 'El detalle no pertenece a esta venta.');
                $previous = (int) $returned->get($detail->id_detalle, 0);
                $next = $previous + $item['cantidad'];
                abort_if($next > (int) $detail->cantidad, 422, 'La cantidad supera las unidades vendidas aún no devueltas.');
                $net = $nets[$detail->id_detalle];
                $value = BigInteger::of($net)->multipliedBy($next)->quotient($detail->cantidad)->toInt()
                    - BigInteger::of($net)->multipliedBy($previous)->quotient($detail->cantidad)->toInt();
                $total += $value;
                $rows[] = ['detalle_venta_id' => $detail->id_detalle, 'producto_id' => $detail->id_producto,
                    'cantidad' => $item['cantidad'], 'condicion' => $item['condicion'],
                    'precio_venta' => $detail->precio_unitario, 'importe_neto' => $value / 100];
                if ($item['condicion'] === 'apto') {
                    $stock[$detail->id_producto] = ($stock[$detail->id_producto] ?? 0) + $item['cantidad'];
                }
            }
            $id = DB::table('g4_devoluciones')->insertGetId(['request_key' => $data['request_key'], 'payload_hash' => $hash,
                'venta_id' => $saleId, 'cliente_id' => $client, 'usuario_id' => $actor->id,
                'motivo' => trim($data['motivo']), 'total' => $total / 100, 'created_at' => now()]);
            foreach ($rows as $row) {
                DB::table('g4_devolucion_detalles')->insert(['devolucion_id' => $id, ...$row]);
            }
            $operation = $stock ? app(StockDeliveryService::class)->enqueue($saleId, $stock, 'devolucion_comercial', $actor->id) : null;
            DB::table('g4_devoluciones')->where('id', $id)->update(['stock_operation_id' => $operation]);
            $ledger = DB::table('cuenta_corriente_movimientos')->insertGetId(['cliente_id' => $client, 'venta_id' => null,
                'tipo' => 'nota_credito', 'descripcion' => 'Devolución #'.$id.' de venta #'.$saleId,
                'importe' => $total / 100, 'referencia_externa' => 'devolucion:'.$id, 'created_at' => now(), 'updated_at' => now()]);
            $note = DB::table('g4_notas_credito')->insertGetId(['numero' => 'NC-DEV-'.$id, 'devolucion_id' => $id,
                'venta_id' => $saleId, 'cliente_id' => $client, 'movimiento_id' => $ledger,
                'total' => $total / 100, 'usuario_id' => $actor->id, 'created_at' => now()]);
            VentaHistorial::create(['id_venta' => $saleId, 'accion' => 'devolucion_comercial', 'estado_anterior' => $sale->estado,
                'estado_nuevo' => $sale->estado, 'motivo' => trim($data['motivo']),
                'datos' => ['devolucion_id' => $id, 'nota_credito_id' => $note, 'importe' => $total / 100, 'items' => $rows], 'id_usuario' => $actor->id]);

            return ['devolucion_id' => $id, 'repetido' => false];
        }, 3);
        $return = DB::table('g4_devoluciones')->where('id', $result['devolucion_id'])->firstOrFail();
        $applied = ! $return->stock_operation_id || app(StockDeliveryService::class)->deliver($return->stock_operation_id);

        return [...$result, 'stock_estado' => $applied ? 'aplicado' : 'pendiente',
            'nota_credito' => DB::table('g4_notas_credito')->where('devolucion_id', $return->id)->first()];
    }

    private function netAmounts($details, int $net, int $discount): array
    {
        $gross = (int) $details->sum(fn ($d) => (int) round($d->subtotal * 100));
        abort_unless($gross > 0 && $net >= 0 && $gross - $discount === $net, 409, 'El total histórico no coincide con sus detalles y descuento; requiere revisión antes de acreditar.');
        $allocations = [];
        $remainders = [];
        $assigned = 0;
        foreach ($details as $d) {
            abort_if((int) $d->cantidad <= 0 || (float) $d->subtotal < 0, 409, 'El detalle histórico contiene cantidades o importes inválidos.');
            [$part, $remainder] = BigInteger::of((int) round($d->subtotal * 100))->multipliedBy($net)->quotientAndRemainder($gross);
            $allocations[$d->id_detalle] = $part->toInt();
            $assigned += $part->toInt();
            $remainders[$d->id_detalle] = $remainder->toInt();
        }
        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $id) {
            if ($assigned >= $net) {
                break;
            }
            $allocations[$id]++;
            $assigned++;
        }

        return $allocations;
    }
}
