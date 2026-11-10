<?php

namespace App\Modules\CuentaCorriente\Services;

class AccountSummary
{
    public static function calculate(array $entries, array $filters): array
    {
        $mode = $filters['modoFecha'] ?? 'todas';
        $from = $mode === 'especifica' ? ($filters['fecha'] ?? '') : ($mode === 'periodo' ? ($filters['fechaDesde'] ?? '') : '');
        $to = $mode === 'especifica' ? ($filters['fecha'] ?? '') : ($mode === 'periodo' ? ($filters['fechaHasta'] ?? '') : '');
        $paid = 0;
        $current = 0;
        $atEnd = 0;
        foreach ($entries as $entry) {
            $amount = (int) round($entry['monto'] * 100);
            $date = substr((string) $entry['fecha'], 0, 10);
            $current += $amount;
            if ($to === '' || $date <= $to) {
                $atEnd += $amount;
            }
            if (($from === '' || $date >= $from) && ($to === '' || $date <= $to)) {
                if (in_array($entry['tipo'] ?? '', ['pago', 'cobro'], true)
                    || str_starts_with((string) ($entry['referencia_externa'] ?? ''), 'reversion_pago:')) {
                    $paid += $amount;
                }
            }
        }

        return ['pagos_netos' => $paid / 100, 'deuda_al_corte' => max(0, -$atEnd) / 100,
            'saldo_a_favor_al_corte' => max(0, $atEnd) / 100, 'deuda_actual' => max(0, -$current) / 100];
    }
}
