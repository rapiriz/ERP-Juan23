<?php

namespace App\Http\Requests\Cobros;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCobroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol->value === 'administrativo';
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:now'],
            'monto_total' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'medio_pago' => ['required', Rule::enum(PaymentMethod::class)],
            'comprobante_nro' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La fecha del pago no puede ser futura.',
            'monto_total.min' => 'El monto debe ser mayor que cero.',
            'monto_total.decimal' => 'El monto puede tener hasta dos decimales.',
        ];
    }
}
