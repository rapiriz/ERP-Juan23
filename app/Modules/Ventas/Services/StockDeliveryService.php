<?php

namespace App\Modules\Ventas\Services;

use App\Modules\Ventas\Contracts\StockGateway;
use App\Modules\Ventas\Integraciones\HttpStockApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Bandeja durable: la intención se guarda junto a la venta, antes de enviar. */
class StockDeliveryService
{
    public function __construct(private StockGateway $stock) {}

    public function enqueue(int $sale, array $changes, string $reason, int $actor): ?int
    {
        $changes = array_filter($changes, fn ($q) => $q !== 0);
        if (! $changes) {
            return null;
        }
        ksort($changes);
        abort_if(DB::table('g4_stock_operations')->where('venta_id', $sale)->where('estado', 'pendiente')->exists(),
            409, 'La venta tiene un ajuste de stock pendiente. Reintentá esa operación antes de continuar.');
        $id = DB::table('g4_stock_operations')->insertGetId(['request_key' => (string) Str::uuid(),
            'venta_id' => $sale, 'actor_id' => $actor, 'motivo' => $reason,
            'cambios' => json_encode($changes, JSON_THROW_ON_ERROR), 'estado' => 'pendiente',
            'intentos' => 0, 'created_at' => now(), 'updated_at' => now()]);
        if (config('ventas.integracion') === 'internal' || config('ventas.integracion') === 'mock') {
            // Mismo motor: fallo de G3 revierte venta, libro, bandeja y movimientos de stock.
            $this->deliver($id, true);
        }

        return $id;
    }

    public function deliver(int $id, bool $throw = false): bool
    {
        try {
            return DB::transaction(function () use ($id) {
                $operation = DB::table('g4_stock_operations')->where('id', $id)->lockForUpdate()->firstOrFail();
                if ($operation->estado === 'aplicado') {
                    return true;
                }
                abort_if(DB::table('g4_stock_operations')->where('venta_id', $operation->venta_id)
                    ->where('id', '<', $id)->where('estado', 'pendiente')->exists(), 409, 'Hay un ajuste anterior pendiente.');
                $changes = json_decode($operation->cambios, true, 512, JSON_THROW_ON_ERROR);
                if ($this->stock instanceof HttpStockApi) {
                    $this->stock->ajustarConClave($changes, (int) $operation->venta_id, $operation->motivo, $operation->request_key);
                } else {
                    abort_unless(config('ventas.integracion') === 'mock', 503, 'El adaptador debe soportar idempotencia.');
                    $this->stock->ajustar($changes, (int) $operation->venta_id, $operation->motivo);
                }
                DB::table('g4_stock_operations')->where('id', $id)->update(['estado' => 'aplicado',
                    'intentos' => $operation->intentos + 1, 'ultimo_error' => null, 'aplicado_at' => now(), 'updated_at' => now()]);

                return true;
            });
        } catch (Throwable $e) {
            DB::table('g4_stock_operations')->where('id', $id)->update(['intentos' => DB::raw('intentos + 1'),
                'ultimo_error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now()]);
            if ($throw) {
                throw $e;
            }
            report($e);

            return false;
        }
    }
}
