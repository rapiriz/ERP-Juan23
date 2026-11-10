<?php

namespace App\Modules\Rendiciones\Services;

use App\Modules\Caja\Models\Caja;
use App\Modules\Caja\Services\CajaService;
use App\Modules\CuentaCorriente\Services\ClientPaymentService;
use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\CuentaCorriente\Services\SalePaymentEvidence;
use App\Modules\Login\Models\User;
use App\Modules\Rendiciones\Models\RendicionCobro;
use App\Modules\Shared\Services\G2OperationAudit;
use App\Modules\Ventas\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Cobro aplicado: caja + libro financiero + comprobante, en la misma transacción. */
class CollectionSettlementService
{
    public function __construct(private ClientPaymentService $payments, private FinancialReadService $finance, private CajaService $cash) {}

    public function apply(RendicionCobro $item, User $actor): int
    {
        return DB::transaction(function () use ($item, $actor) {
            $existing = DB::table('cobro')->where('rendicion_cobro_id', $item->id_rendicion_cobro)->first();
            if ($existing) {
                abort_unless($existing->estado === 'registrado' && DB::table('g2_cobro_aplicaciones')->where('id_cobro', $existing->id_cobro)->exists(), 409, 'El cobro existente necesita revisión de su aplicación financiera.');

                return (int) $existing->id_cobro;
            }
            $reference = 'rendicion_cobro:'.$item->id_rendicion_cobro;
            $legacyCash = 'Liquidación Rendición #'.$item->id_rendicion.' - Cobro #'.$item->id_rendicion_cobro;
            abort_if(DB::table('cuenta_corriente_movimientos')->where('cliente_id', $item->id_cliente)->where('referencia_externa', $reference)->where('importe', '>', 0)->exists()
                || DB::table('CAJA_MOVIMIENTO')->where('concepto', $legacyCash)->exists(), 409, 'Hay una aplicación histórica de este cobro que necesita vinculación antes de liquidar nuevamente.');
            DB::table('clientes')->where('id', $item->id_cliente)->lockForUpdate()->firstOrFail();
            $sale = Venta::lockForUpdate()->findOrFail($item->id_factura);
            abort_unless((int) $sale->id_cliente === (int) $item->id_cliente && ! in_array($sale->estado, ['pendiente', 'cancelada', 'anulada'], true), 422, 'La venta no corresponde al cliente o no admite cobros.');
            $amount = (int) round($item->monto * 100);
            $pending = $this->finance->account($item->id_cliente)['invoices']->firstWhere('id', (int) $item->id_factura);
            abort_unless($pending && $amount > 0 && $amount <= (int) round($pending['importe_pendiente'] * 100), 422, 'El cobro excede el pendiente del comprobante.');
            $details = $item->datos_medio_pago ?? [];
            if ($item->medio_pago === 'cheque') {
                $validation = validator($details, ['numero_cheque' => 'required|string|max:50', 'banco_emisor' => 'required|string|max:100', 'fecha_cobro' => 'required|date_format:Y-m-d']);
                if ($validation->fails()) {
                    throw new ValidationException($validation);
                }
            }
            $key = (string) Str::uuid();
            $result = $this->payments->register($item->id_cliente, ['request_key' => $key, 'monto' => $amount / 100, 'medio_pago' => $item->medio_pago,
                'comprobante' => 'Rendición #'.$item->id_rendicion.' · cobro #'.$item->id_rendicion_cobro,
                'datos_medio_pago' => $details, 'rendicion_cobro_id' => $item->id_rendicion_cobro, 'id_factura' => $sale->id_venta], $actor);
            $payment = DB::table('cuenta_corriente_pagos')->where('id', $result['pago_id'])->first();
            $id = $result['id_cobro'];
            $item->forceFill(['estado_revision' => 'aplicado', 'id_cobro_aplicado' => $id, 'revisado_por' => $actor->id, 'fecha_revision' => now()])->save();
            G2OperationAudit::record('cobros', $id, 'aplicado', $actor->id, ['rendicion_cobro_id' => $item->id_rendicion_cobro, 'pago_id' => $payment->id, 'venta_id' => $sale->id_venta, 'monto' => $amount / 100]);

            // La fase logística de Ventas no se usa como saldo ni se fuerza "pagada" por un pago parcial.
            return $id;
        });
    }

    public function cancel(int $id, User $actor, string $reason): array
    {
        abort_unless($actor->rol->value === 'administrativo' && $actor->estado->value === 'activo', 403);
        abort_if(mb_strlen(trim($reason)) < 3, 422, 'Indicá un motivo de anulación.');

        return DB::transaction(function () use ($id, $actor, $reason) {
            $collection = DB::table('cobro')->where('id_cobro', $id)->lockForUpdate()->firstOrFail();
            if ($collection->estado === 'anulado') {
                return ['id_cobro' => $id, 'repetido' => true];
            }
            $cashIds = DB::table('CAJA_MOVIMIENTO')->where('id_cobro', $id)->pluck('id_movimiento_caja');
            $checkIds = DB::table('CHEQUE')->where('id_cobro', $id)->pluck('id_cheque');
            abort_if(DB::table('CONCILIACION_DETALLE')->where(fn ($q) => $q->where('id_cobro', $id)->orWhereIn('id_movimiento_caja', $cashIds)->orWhereIn('id_cheque', $checkIds))->exists(), 409, 'Primero debe revertirse la conciliación del cobro.');
            $link = DB::table('g2_cobro_aplicaciones')->where('id_cobro', $id)->lockForUpdate()->first();
            abort_unless($link, 409, 'Cobro histórico sin vínculo financiero verificable: requiere revisión, no se generará una deuda ficticia.');
            $payment = DB::table('cuenta_corriente_pagos')->where('id', $link->pago_id)->firstOrFail();
            DB::table('clientes')->where('id', $collection->id_cliente)->lockForUpdate()->firstOrFail();
            $cash = $this->cash->registrarReversionDeCobro($actor->id, Caja::ROL_ADMIN, (float) $collection->monto_total, 'Anulación cobro #'.$id.': '.$reason, $id, $collection->medio_pago);
            abort_if(($cash['error'] ?? false) || empty($cash['id_movimiento_caja']), 422, $cash['mensaje'] ?? 'No se pudo revertir en caja.');
            $ledger = DB::table('cuenta_corriente_movimientos')->insertGetId(['cliente_id' => $collection->id_cliente, 'venta_id' => null, 'tipo' => 'ajuste',
                'importe' => -(float) $collection->monto_total, 'descripcion' => 'Reversión cobro #'.$id.': '.$reason, 'referencia_externa' => 'reversion_pago:'.$payment->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('g2_cobro_aplicaciones')->where('id_cobro', $id)->update(['reversion_cuenta_id' => $ledger, 'reversion_caja_id' => $cash['id_movimiento_caja']]);
            DB::table('cobro')->where('id_cobro', $id)->update(['estado' => 'anulado', 'motivo_anulacion' => $reason, 'fecha_anulacion' => now(), 'id_usuario_anulacion' => $actor->id]);
            DB::table('CHEQUE')->where('id_cobro', $id)->update(['estado' => 'anulado']);
            if ($collection->rendicion_cobro_id) {
                RendicionCobro::whereKey($collection->rendicion_cobro_id)->update(['estado_revision' => 'anulado', 'nota_revision' => $reason, 'fecha_revision' => now(), 'revisado_por' => $actor->id]);
            }
            foreach (DB::table('cobro_venta')->where('id_cobro', $id)->pluck('id_venta')->unique()->sort() as $saleId) {
                app(SalePaymentEvidence::class)->refreshAfterReversal(
                    (int) $saleId, (int) $actor->id, 'Anulación cobro #'.$id.': '.$reason);
            }
            G2OperationAudit::record('cobros', $id, 'anulado', $actor->id, ['motivo' => $reason, 'reversion_cuenta_id' => $ledger, 'reversion_caja_id' => $cash['id_movimiento_caja']]);

            return ['id_cobro' => $id, 'repetido' => false, 'saldo_restante' => $this->finance->account($collection->id_cliente)['balance']['saldo']];
        });
    }
}
