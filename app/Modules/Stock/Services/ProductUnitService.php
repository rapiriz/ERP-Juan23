<?php

namespace App\Modules\Stock\Services;

use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Las equivalencias pertenecen a un producto; es_base es la selección visual. */
class ProductUnitService
{
    public static function resolve(int $productId, ?int $unitId = null): UnidadMedida
    {
        return DB::transaction(function () use ($productId, $unitId) {
            Producto::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            $units = UnidadMedida::query()->where('id_producto', $productId);
            if ($unitId !== null) {
                $unit = $units->whereKey($unitId)->lockForUpdate()->first();
                if (! $unit) {
                    throw ValidationException::withMessages(['id_unidad' => 'La unidad no pertenece al producto indicado.']);
                }

                return $unit;
            }

            // Omitir unidad significa unidades individuales, nunca la caja visual activa.
            return $units->where('equivalencia_base', 1)->orderBy('id_unidad')->first()
                ?? UnidadMedida::create(['id_producto' => $productId, 'nombre_unidad' => 'Unidad individual',
                    'equivalencia_base' => 1, 'es_base' => 0, 'descripcion' => 'Unidad individual del producto']);
        });
    }

    public static function quantity(int $quantity, int $productId, ?int $unitId): int
    {
        $factor = $unitId === null ? 1.0 : (float) self::resolve($productId, $unitId)->equivalencia_base;
        $base = $quantity * $factor;
        if (! is_finite($base) || $factor <= 0 || $base <= 0 || $base > 2147483647 || abs($base - round($base)) > 0.000001) {
            throw ValidationException::withMessages(['cantidad' => 'La conversión debe producir una cantidad entera positiva de unidades individuales.']);
        }

        return (int) round($base);
    }
}
