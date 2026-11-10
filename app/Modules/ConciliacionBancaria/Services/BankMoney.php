<?php

namespace App\Modules\ConciliacionBancaria\Services;

use InvalidArgumentException;

class BankMoney
{
    /** Importes localizados a centavos; no convierte prefijos numéricos parciales. */
    public static function cents(mixed $value): int
    {
        if (is_int($value) || is_float($value)) {
            if (! is_finite((float) $value) || abs($value) > 9999999999.99 || abs($value * 100 - round($value * 100)) > 0.0001) {
                throw new InvalidArgumentException('Importe numérico fuera de rango o con más de dos decimales.');
            }

            return (int) round($value * 100);
        }
        $value = trim(str_replace(["\xc2\xa0", '$'], '', (string) $value));
        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        if ($negative) {
            $value = substr($value, 1, -1);
        }
        if (preg_match('/^[+-]?\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/D', $value)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (preg_match('/^[+-]?\d+(?:,\d{1,2})?$/D', $value)) {
            $value = str_replace(',', '.', $value);
        } elseif (! preg_match('/^[+-]?\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Formato de importe inválido o ambiguo.');
        }
        $number = (float) $value;
        if ($negative) {
            $number = -$number;
        }

        return self::cents($number);
    }
}
