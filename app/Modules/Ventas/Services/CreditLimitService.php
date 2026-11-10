<?php

namespace App\Modules\Ventas\Services;

use App\Modules\CuentaCorriente\Services\FinancialReadService;
use App\Modules\Ventas\Exceptions\CreditLimitExceeded;
use Illuminate\Support\Facades\DB;

class CreditLimitService
{
    public function status(int $client): array
    {
        $record = DB::table('clientes')->where('id', $client)->firstOrFail();
        $account = app(FinancialReadService::class)->account($client);
        $pending = $account['payments']->filter(fn ($p) => isset($p['cheque']) && $p['cheque']['estado'] !== 'conciliado')
            ->sum(fn ($p) => (int) round($p['monto_total'] * 100));
        $unverified = $account['payments']->filter(fn ($p) => empty($p['id_cobro']))
            ->sum(fn ($p) => (int) round($p['monto_total'] * 100));
        $unverifiedNotes = DB::table('cuenta_corriente_movimientos')->where('cliente_id', $client)
            ->where('tipo', 'nota_credito')->where('importe', '>', 0)->get()
            ->reject(fn ($entry) => app(\App\Modules\CuentaCorriente\Services\CreditNoteEvidence::class)->verified($entry))
            ->sum(fn ($entry) => (int) round($entry->importe * 100));
        $netExposure = -(int) round($account['balance']['saldo_contable'] * 100) + $pending + $unverified + $unverifiedNotes;
        $exposure = max(0, $netExposure);
        $limit = $record->limite_credito === null ? null : (int) round($record->limite_credito * 100);

        return ['cliente_id' => $client, 'limite_credito' => $limit === null ? null : $limit / 100,
            'exposicion' => $exposure / 100, 'cheques_pendientes' => $pending / 100,
            'pagos_sin_respaldo' => $unverified / 100,
            'notas_credito_sin_respaldo' => $unverifiedNotes / 100,
            'saldo_a_favor_aplicable' => max(0, -$netExposure) / 100,
            'credito_disponible' => $limit === null ? null : max(0, $limit - $netExposure) / 100,
            'excedido' => $limit !== null && $exposure > $limit];
    }

    public function requireAvailable(int $client): void
    {
        DB::table('clientes')->where('id', $client)->lockForUpdate()->firstOrFail();
        $status = $this->status($client);
        if ($status['excedido']) {
            throw new CreditLimitExceeded($status);
        }
    }

    public function blocked(int $sale, int $actor, array $status): void
    {
        DB::table('g4_credit_events')->insert(['cliente_id' => $status['cliente_id'], 'venta_id' => $sale,
            'usuario_id' => $actor, 'evento' => 'confirmacion_bloqueada', 'datos' => json_encode($status, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
