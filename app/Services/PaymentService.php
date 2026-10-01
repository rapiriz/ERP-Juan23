<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function register(Cliente $cliente, User $responsable, array $data): Cobro
    {
        return DB::transaction(function () use ($cliente, $responsable, $data): Cobro {
            $lockedClient = Cliente::query()->lockForUpdate()->findOrFail($cliente->id);
            $amount = round((float) $data['monto_total'], 2);
            $balance = round((float) $lockedClient->saldo, 2);

            if ($balance <= 0) {
                throw ValidationException::withMessages([
                    'monto_total' => 'El cliente no tiene saldo pendiente.',
                ]);
            }

            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'monto_total' => 'El pago no puede superar el saldo pendiente de $'.number_format($balance, 2, ',', '.'),
                ]);
            }

            $cobro = Cobro::query()->create([
                ...$data,
                'monto_total' => $amount,
                'cliente_id' => $lockedClient->id,
                'usuario_id' => $responsable->id,
                'estado' => PaymentStatus::CONFIRMADO,
            ]);

            $lockedClient->saldo = $balance - $amount;
            $lockedClient->save();

            $this->applyToOldestSales($cobro, $amount);

            return $cobro;
        });
    }

    private function applyToOldestSales(Cobro $cobro, float $remaining): void
    {
        $sales = $cobro->cliente->ventas()
            ->whereNotIn('estado', [SaleStatus::CANCELADA->value])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            if ($remaining <= 0) {
                break;
            }

            $alreadyApplied = (float) DB::table('cobro_ventas')
                ->where('venta_id', $sale->id)
                ->sum('monto_aplicado');
            $saleBalance = max(0, round((float) $sale->total - $alreadyApplied, 2));

            if ($saleBalance === 0.0) {
                continue;
            }

            $applied = min($remaining, $saleBalance);
            $cobro->ventas()->attach($sale->id, ['monto_aplicado' => $applied]);
            $remaining = round($remaining - $applied, 2);
        }
    }
}
