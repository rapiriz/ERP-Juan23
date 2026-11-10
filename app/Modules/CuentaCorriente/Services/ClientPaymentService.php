<?php

namespace App\Modules\CuentaCorriente\Services;

use App\Modules\Caja\Models\Caja;
use App\Modules\Caja\Services\CajaService;
use App\Modules\Clientes\Models\Cliente;
use App\Modules\Login\Enums\UserRole;
use App\Modules\Login\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Caso de uso de G4 compartido por su API y la ficha web de G1. */
class ClientPaymentService
{
    public function __construct(private FinancialReadService $finance, private CajaService $cash) {}

    public function register(int $clientId, array $data, User $actor): array
    {
        abort_unless($actor->rol === UserRole::ADMINISTRATIVO && $actor->estado->value === 'activo', 403);

        return DB::transaction(function () use ($clientId, $data, $actor): array {
            $client = Cliente::query()->whereKey($clientId)->lockForUpdate()->firstOrFail();
            $amount = (int) round($data['monto'] * 100);
            $details = $data['datos_medio_pago'] ?? [];
            if ($data['medio_pago'] === 'cheque') {
                validator($details, ['numero_cheque' => 'required|string|max:50', 'banco_emisor' => 'required|string|max:100', 'fecha_cobro' => 'required|date_format:Y-m-d'])->validate();
            }
            $existing = DB::table('cuenta_corriente_pagos')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless((int) $existing->cliente_id === $clientId && (int) $existing->usuario_id === (int) $actor->id
                    && (int) round($existing->monto * 100) === $amount && $existing->medio_pago === $data['medio_pago']
                    && ($existing->comprobante ?? '') === ($data['comprobante'] ?? '')
                    && (int) ($existing->transferencia_id ?? 0) === (int) ($data['transferencia_id'] ?? 0), 409, 'La referencia de solicitud ya se utilizó para otro pago.');

                $collection = DB::table('g2_cobro_aplicaciones')->where('pago_id', $existing->id)->value('id_cobro');
                if ($data['medio_pago'] === 'cheque') {
                    $check = DB::table('CHEQUE')->where('id_cobro', $collection)->first();
                    abort_unless($check && $check->numero_cheque === $details['numero_cheque'] && $check->banco_emisor === $details['banco_emisor']
                        && substr((string) $check->fecha_cobro, 0, 10) === $details['fecha_cobro'], 409, 'La referencia pertenece a otro cheque.');
                }

                return ['id_cobro' => $collection, 'pago_id' => $existing->id, 'saldo_restante' => (float) $existing->saldo_restante, 'repetido' => true];
            }
            if ($client->estado->value !== 'activo') {
                throw ValidationException::withMessages(['monto' => 'El cliente está inactivo.']);
            }
            $account = $this->finance->account($clientId);
            $debt = (int) round($account['balance']['saldo'] * 100);
            if ($amount <= 0 || $amount > $debt) {
                throw ValidationException::withMessages(['monto' => 'El pago debe ser positivo y no puede superar la deuda pendiente.']);
            }
            if (isset($data['transferencia_id'])) {
                $notice = DB::table('g1_transferencias')->where('id', $data['transferencia_id'])->lockForUpdate()->first();
                if (! $notice || (int) $notice->cliente_id !== $clientId || $notice->estado !== 'validada'
                    || $data['medio_pago'] !== 'transferencia' || (int) round($notice->monto * 100) !== $amount) {
                    throw ValidationException::withMessages(['transferencia_id' => 'El aviso debe estar validado y coincidir con cliente, medio e importe.']);
                }
                abort_if(DB::table('cuenta_corriente_pagos')->where('transferencia_id', $notice->id)->exists(), 409, 'Esta transferencia ya tiene un pago registrado.');
            }
            // Se toma la misma caja que usa el servicio de G2 y se mantiene bloqueada hasta terminar.
            $open = Caja::query()->where('id_usuario', $actor->id)->where('estado', Caja::ESTADO_ABIERTA)->orderByDesc('fecha')->lockForUpdate()->first();
            if (! $open) {
                throw ValidationException::withMessages(['caja' => 'Abrí tu caja antes de registrar el pago.']);
            }
            $movement = $this->cash->registrarCobro($actor->id, Caja::ROL_ADMIN, $amount / 100,
                'Pago de cliente #'.$clientId.' — '.$data['request_key'], $data['medio_pago']);
            if (! $movement || ($movement['error'] ?? false)) {
                throw ValidationException::withMessages(['caja' => $movement['mensaje'] ?? 'No se pudo registrar el cobro en caja.']);
            }
            $now = now();
            $ledger = DB::table('cuenta_corriente_movimientos')->insertGetId([
                'cliente_id' => $clientId, 'venta_id' => null, 'tipo' => 'pago', 'importe' => $amount / 100,
                'descripcion' => 'Pago de cuenta — '.($data['comprobante'] ?? 'Sin comprobante'),
                'referencia_externa' => ucfirst($data['medio_pago']), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $payment = ['request_key' => $data['request_key'], 'cliente_id' => $clientId, 'usuario_id' => $actor->id,
                'monto' => $amount / 100, 'medio_pago' => $data['medio_pago'], 'comprobante' => $data['comprobante'] ?? null,
                'transferencia_id' => $data['transferencia_id'] ?? null,
                'movimiento_cuenta_id' => $ledger, 'movimiento_caja_id' => $movement['id_movimiento_caja'],
                'saldo_anterior' => $debt / 100, 'saldo_restante' => ($debt - $amount) / 100, 'created_at' => $now];
            $id = DB::table('cuenta_corriente_pagos')->insertGetId($payment);
            $collection = DB::table('cobro')->insertGetId([
                'id_cliente' => $clientId, 'id_usuario' => $actor->id, 'fecha' => $now,
                'monto_total' => $amount / 100, 'medio_pago' => $data['medio_pago'], 'estado' => 'registrado',
                'comprobante_nro' => mb_substr($data['comprobante'] ?? ('PAGO-'.$id), 0, 100),
                'clave_operacion' => 'pago:'.$data['request_key'], 'rendicion_cobro_id' => $data['rendicion_cobro_id'] ?? null,
            ], 'id_cobro');
            DB::table('g2_cobro_aplicaciones')->insert(['id_cobro' => $collection, 'pago_id' => $id, 'created_at' => $now]);
            DB::table('CAJA_MOVIMIENTO')->where('id_movimiento_caja', $payment['movimiento_caja_id'])->update(['id_cobro' => $collection]);
            $remaining = $amount;
            $invoices = $account['invoices'];
            if (isset($data['id_factura'])) {
                $invoices = $invoices->where('id', (int) $data['id_factura']);
            }
            foreach ($invoices as $invoice) {
                $allocated = min($remaining, (int) round($invoice['importe_pendiente'] * 100));
                if ($allocated > 0) {
                    DB::table('cobro_venta')->insert(['id_cobro' => $collection, 'id_venta' => $invoice['id'], 'monto_aplicado' => $allocated / 100]);
                    $remaining -= $allocated;
                }
            }
            abort_unless($remaining === 0, 422, 'El pago no puede asignarse a las ventas pendientes indicadas.');
            if (isset($data['rendicion_cobro_id'])) {
                DB::table('cuenta_corriente_movimientos')->where('id', $ledger)->update(['referencia_externa' => 'rendicion_cobro:'.$data['rendicion_cobro_id']]);
            }
            if ($data['medio_pago'] === 'cheque') {
                DB::table('CHEQUE')->insert(['id_cobro' => $collection, 'numero_cheque' => $details['numero_cheque'],
                    'banco_emisor' => $details['banco_emisor'], 'fecha_cobro' => $details['fecha_cobro'], 'estado' => 'en_cartera']);
            }
            DB::table('cuenta_corriente_pago_eventos')->insert(['pago_id' => $id, 'usuario_id' => $actor->id,
                'evento' => 'pago_aplicado', 'datos' => json_encode($payment, JSON_THROW_ON_ERROR), 'created_at' => $now]);

            return ['id_cobro' => $collection, 'pago_id' => $id, 'saldo_restante' => ($debt - $amount) / 100, 'repetido' => false];
        });
    }
}
