<?php

namespace App\Modules\Promociones\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Revalida el combo completo y sus precios al crear/modificar/confirmar. */
class PromotionSaleService
{
    public function validate(array $items, array $catalog, string $priceColumn): void
    {
        $groups = collect($items)->filter(fn ($i) => ! empty($i['id_promocion']))->groupBy('id_promocion')->sortKeys();
        foreach ($groups as $id => $lines) {
            $promo = DB::table('promocion')->where('id_promocion', $id)->lockForUpdate()->first();
            $today = today()->toDateString();
            $fail = fn ($message) => throw ValidationException::withMessages(['items' => $message]);
            if (! $promo || (int) $promo->estado !== 1 || substr($promo->vigencia_desde, 0, 10) > $today
                || substr($promo->vigencia_hasta, 0, 10) < $today) {
                $fail('La promoción no existe, está inactiva o está fuera de vigencia.');
            }
            $recipe = DB::table('promocion_producto')->where('id_promocion', $id)->orderBy('id_producto')->lockForUpdate()->get();
            $byProduct = $lines->keyBy('id');
            if ($recipe->isEmpty() || $lines->count() !== $byProduct->count()
                || $recipe->pluck('id_producto')->sort()->values()->all() != $byProduct->keys()->sort()->values()->all()) {
                $fail('La promoción requiere todos sus productos, sin duplicados ni productos ajenos.');
            }
            $multiple = null;
            $gross = 0;
            $parts = [];
            foreach ($recipe as $component) {
                $line = $byProduct->get($component->id_producto);
                $qty = (int) $line['cantidad'];
                $required = (int) $component->cantidad;
                if ($required <= 0 || $qty <= 0 || $qty % $required !== 0 || ! isset($catalog[$component->id_producto])) {
                    $fail('Las cantidades no completan el combo promocional.');
                }
                $factor = intdiv($qty, $required);
                $multiple ??= $factor;
                if ($multiple !== $factor) {
                    $fail('Todos los productos deben corresponder a la misma cantidad de combos.');
                }
                $price = (int) round($catalog[$component->id_producto][$priceColumn] * 100);
                $parts[] = ['line' => $line, 'price' => $price, 'qty' => $required, 'gross' => $price * $required];
                $gross += $price * $required;
            }
            $value = (float) $promo->valor_descuento;
            if (! is_finite($value) || $value < 0 || ! in_array($promo->tipo_descuento, ['porcentaje', 'monto_fijo'], true)
                || ($promo->tipo_descuento === 'porcentaje' && $value > 100)) {
                $fail('La configuración del descuento promocional es inválida.');
            }
            $budget = $promo->tipo_descuento === 'porcentaje' ? (int) round($gross * $value / 100)
                : (int) round($value * 100);
            if ($budget > $gross) {
                $fail('El descuento promocional supera el precio actual del combo.');
            }
            $remaining = $budget;
            foreach ($parts as $index => $part) {
                $assigned = $index === count($parts) - 1 ? $remaining
                    : min($remaining, (int) round($budget * $part['gross'] / max(1, $gross)));
                $remaining -= $assigned;
                $unitDiscount = min($part['price'], intdiv($assigned, $part['qty']));
                if ((int) round($part['line']['descuento'] * 100) !== $unitDiscount) {
                    $fail('El descuento no coincide con el autorizado por la promoción y la lista actual. Actualizá el carrito.');
                }
            }
        }
    }
}
