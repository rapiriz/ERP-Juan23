<?php

namespace App\Modules\Ventas\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** La tarifa mayorista es una elección explícita, no un descuento por cantidad. */
class SalePricePolicy
{
    public function customerType(array $customer): string
    {
        $type = $customer['tipo_cliente'] ?? null;
        if ($type === null || $type === '') {
            $type = DB::table('clientes')->where('id', $customer['id'])->value('tipo_cliente');
        }

        return $type instanceof \BackedEnum ? $type->value : (string) $type;
    }

    public function validate(array $customer, string $list, bool $applyWholesale): void
    {
        $local = DB::table('clientes')->where('id', $customer['id'])->first();
        $status = $local->estado ?? $customer['estado'] ?? null;
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }
        if ($status !== 'activo') {
            throw ValidationException::withMessages(['cliente_id' => 'El cliente debe estar activo para registrar o modificar una venta.']);
        }
        if (($list === 'mayorista') !== $applyWholesale) {
            throw ValidationException::withMessages(['aplicar_precio_mayorista' => 'Para usar la tarifa mayorista debés marcar Aplicar precio mayorista.']);
        }
        if ($applyWholesale && ($local->tipo_cliente ?? $this->customerType($customer)) !== 'mayorista') {
            throw ValidationException::withMessages(['lista' => 'La tarifa mayorista sólo puede aplicarse a un cliente mayorista.']);
        }
    }
}
