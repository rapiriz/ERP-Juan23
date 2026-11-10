<?php

namespace App\Modules\CuentaCorriente\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Lectura del libro de cuenta corriente. Los cobros declarados no acreditan saldo. */
class FinancialReadService
{
    public function account(int $clientId, ?Collection $entries = null, ?Collection $sales = null, ?Collection $collectionInvoices = null): array
    {
        $entries ??= DB::table('cuenta_corriente_movimientos')->where('cliente_id', $clientId)
            ->orderBy('created_at')->orderBy('id')->get();
        $sales ??= DB::table('venta')->where('id_cliente', $clientId)
            ->whereNotIn('estado', ['cancelada', 'anulada'])->orderBy('fecha')->orderBy('id_venta')->get();
        $postedSales = $entries->whereNotNull('venta_id')->keyBy('venta_id');
        $signed = (int) $entries->sum(fn ($entry) => (int) round($entry->importe * 100));
        $historicalEntries = [];
        $collectionInvoices ??= DB::table('RENDICION_COBRO')->pluck('id_factura', 'id_rendicion_cobro');
        $invoices = [];

        foreach ($sales as $sale) {
            $posted = $postedSales->get($sale->id_venta);
            $amount = $posted ? max(0, -(int) round($posted->importe * 100)) : (int) round($sale->total * 100);
            if (! $posted) {
                // Ventas históricas anteriores al libro se leen sin escribir un movimiento nuevo.
                $signed -= $amount;
                $historicalEntries[] = ['fecha' => (string) $sale->fecha,
                    'descripcion' => 'Venta histórica '.$sale->numFactura, 'monto' => -$amount / 100,
                    'venta_id' => $sale->id_venta, 'referencia_externa' => 'Reconstrucción de lectura'];
            }
            $invoices[$sale->id_venta] = [
                'id' => (int) $sale->id_venta,
                'cliente_id' => $clientId,
                'numero' => (string) $sale->numFactura,
                'tipo_comprobante' => 'venta_interna',
                'fecha' => (string) $sale->fecha,
                'vencimiento' => $sale->fecha_vencimiento ?? null,
                'pendiente_centavos' => $amount,
            ];
        }

        $unallocatedCredit = 0;
        $payments = [];
        $registered = Schema::hasTable('cuenta_corriente_pagos')
            ? DB::table('cuenta_corriente_pagos')->where('cliente_id', $clientId)->get()->keyBy('movimiento_cuenta_id') : collect();
        $reversed = Schema::hasTable('g2_cobro_aplicaciones') ? DB::table('g2_cobro_aplicaciones')
            ->whereNotNull('reversion_cuenta_id')->get() : collect();
        $reversedPayments = $reversed->pluck('pago_id')->all();
        $reversalEntries = $reversed->pluck('reversion_cuenta_id')->all();
        $collectionLinks = Schema::hasTable('g2_cobro_aplicaciones')
            ? DB::table('g2_cobro_aplicaciones')->whereIn('pago_id', $registered->pluck('id'))->pluck('id_cobro', 'pago_id') : collect();
        $cheques = Schema::hasTable('CHEQUE')
            ? DB::table('CHEQUE')->whereIn('id_cobro', $collectionLinks->values())->get()->keyBy('id_cobro') : collect();
        foreach ($entries->whereNull('venta_id') as $entry) {
            if (in_array($entry->id, $reversalEntries) || in_array($registered->get($entry->id)?->id, $reversedPayments)) {
                continue;
            }
            $amount = (int) round($entry->importe * 100);
            $reference = (string) $entry->referencia_externa;
            $invoiceId = null;
            if ($amount > 0 && str_starts_with($reference, 'rendicion_cobro:')) {
                $collectionId = (int) substr($reference, strlen('rendicion_cobro:'));
                $invoiceId = $collectionInvoices->get($collectionId);
            }
            if ($amount > 0 && str_starts_with($reference, 'devolucion:')) {
                $invoiceId = DB::table('g4_notas_credito')->where('movimiento_id', $entry->id)->where('cliente_id', $clientId)->value('venta_id');
            }
            if ($amount > 0 && $invoiceId !== null && isset($invoices[$invoiceId])) {
                $applied = min($amount, $invoices[$invoiceId]['pendiente_centavos']);
                $invoices[$invoiceId]['pendiente_centavos'] -= $applied;
                $unallocatedCredit += $amount - $applied;
            } else {
                $unallocatedCredit += $amount;
            }
            if ($amount > 0 && in_array($entry->tipo, ['pago', 'cobro'], true)) {
                $collectionId = $collectionLinks->get($registered->get($entry->id)?->id);
                $cheque = $cheques->get($collectionId);
                $payments[] = [
                    'id' => (int) $entry->id,
                    'cliente_id' => $clientId,
                    'fecha' => $entry->created_at,
                    'monto_total' => round($amount / 100, 2),
                    'medio_pago' => str_starts_with($reference, 'rendicion_cobro:') ? 'Rendición aplicada' : ($reference !== '' ? $reference : 'Sin informar'),
                    'observaciones' => $entry->descripcion,
                    'confirmacion_individual' => true,
                    'estado' => 'aplicado',
                    'id_cobro' => $collectionId ? (int) $collectionId : null,
                    'cheque' => $cheque ? ['id' => (int) $cheque->id_cheque, 'estado' => $cheque->estado,
                        'fecha_cobro' => substr((string) $cheque->fecha_cobro, 0, 10)] : null,
                    'rendicion_cobro_id' => str_starts_with($reference, 'rendicion_cobro:') ? (int) substr($reference, strlen('rendicion_cobro:')) : null,
                    'comprobante_nro' => $registered->get($entry->id)?->comprobante,
                    'registrado_por' => $registered->get($entry->id)?->usuario_id,
                    'origen' => 'cuenta_corriente',
                ];
            }
        }

        // Pagos y créditos sin factura explícita se distribuyen por antigüedad.
        foreach ($invoices as &$invoice) {
            $applied = min(max(0, $unallocatedCredit), $invoice['pendiente_centavos']);
            $invoice['pendiente_centavos'] -= $applied;
            $unallocatedCredit -= $applied;
            $invoice['importe_pendiente'] = round($invoice['pendiente_centavos'] / 100, 2);
            unset($invoice['pendiente_centavos']);
        }
        unset($invoice);

        return [
            'historical_entries' => $historicalEntries,
            'balance' => [
                'cliente_id' => $clientId,
                'saldo' => max(0, -$signed) / 100,
                'saldo_a_favor' => max(0, $signed) / 100,
                'saldo_contable' => $signed / 100,
                'moneda' => 'ARS',
                'origen' => 'cuenta_corriente',
            ],
            'invoices' => collect($invoices)->filter(fn ($invoice) => $invoice['importe_pendiente'] > 0)->values(),
            'payments' => collect($payments)->sortByDesc('fecha')->values(),
        ];
    }

    public function accounts(): Collection
    {
        $entries = DB::table('cuenta_corriente_movimientos')->orderBy('created_at')->orderBy('id')->get()->groupBy('cliente_id');
        $sales = DB::table('venta')->whereNotIn('estado', ['cancelada', 'anulada'])
            ->orderBy('fecha')->orderBy('id_venta')->get()->groupBy('id_cliente');
        $collections = DB::table('RENDICION_COBRO')->pluck('id_factura', 'id_rendicion_cobro');

        return DB::table('clientes')->orderBy('id')->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => $this->account(
            (int) $id, $entries->get($id, collect()), $sales->get($id, collect()), $collections)]);
    }
}
