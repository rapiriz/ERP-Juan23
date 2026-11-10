<?php

namespace App\Modules\CuentaCorriente\Services;

use App\Modules\Ventas\Models\Venta;
use App\Modules\Ventas\Models\VentaHistorial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalePaymentEvidence
{
    public function requireSettled(int $clientId, int $saleId): void
    {
        // Mismo orden de bloqueo que los pagos: cliente antes de venta.
        DB::table('clientes')->where('id', $clientId)->lockForUpdate()->firstOrFail();
        $entries = DB::table('cuenta_corriente_movimientos')->where('cliente_id', $clientId)->orderBy('created_at')->orderBy('id')->get();
        $payments = DB::table('cuenta_corriente_pagos')->where('cliente_id', $clientId)->get()->keyBy('movimiento_cuenta_id');
        $verified = $entries->filter(function ($entry) use ($payments) {
            if ($entry->tipo === 'nota_credito' && (float) $entry->importe > 0) {
                return app(CreditNoteEvidence::class)->verified($entry);
            }
            if ($entry->venta_id !== null || (float) $entry->importe <= 0) {
                return true;
            }
            $payment = $payments->get($entry->id);
            if (! $payment || (int) round($payment->monto * 100) !== (int) round($entry->importe * 100)) {
                return false;
            }
            if ($payment->medio_pago === 'cheque') {
                $cobro = DB::table('g2_cobro_aplicaciones')->where('pago_id', $payment->id)->value('id_cobro');
                if (! $cobro || ! DB::table('CHEQUE')->where('id_cobro', $cobro)->where('estado', 'conciliado')->exists()) {
                    return false;
                }
            }

            return DB::table('CAJA_MOVIMIENTO')->where('id_movimiento_caja', $payment->movimiento_caja_id)
                ->where('monto', $payment->monto)->where('tipo', 'cobro')->exists();
        });
        $account = app(FinancialReadService::class)->account($clientId, $verified);
        if ($account['invoices']->contains(fn ($invoice) => $invoice['id'] === $saleId)) {
            throw ValidationException::withMessages(['estado' => 'La venta tiene deuda pendiente o carece de pagos verificables en cuenta corriente y caja.']);
        }
    }

    /** Una reversión no deja una venta como pagada si perdió el respaldo financiero. */
    public function refreshAfterReversal(int $saleId, int $actor, string $reason): void
    {
        $client = DB::table('venta')->where('id_venta', $saleId)->value('id_cliente');
        if ($client === null) {
            return;
        }
        DB::table('clientes')->where('id', $client)->lockForUpdate()->firstOrFail();
        $sale = Venta::lockForUpdate()->findOrFail($saleId);
        if ($sale->estado !== 'pagada') {
            return;
        }
        try {
            $this->requireSettled((int) $client, $saleId);

            return;
        } catch (ValidationException) {
            $previous = VentaHistorial::where('id_venta', $saleId)
                ->where('estado_nuevo', 'pagada')->orderByDesc('id')->value('estado_anterior');
            abort_unless(in_array($previous, ['pendiente', 'confirmada', 'preparada', 'entregada'], true), 409,
                'La venta histórica pagada no tiene una fase anterior verificable; requiere revisión antes de revertir.');
            $sale->update(['estado' => $previous]);
            VentaHistorial::create(['id_venta' => $saleId, 'accion' => 'reversion_financiera',
                'estado_anterior' => 'pagada', 'estado_nuevo' => $previous, 'motivo' => $reason, 'id_usuario' => $actor]);
        }
    }
}
