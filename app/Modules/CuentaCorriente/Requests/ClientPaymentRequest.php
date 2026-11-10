<?php

namespace App\Modules\CuentaCorriente\Requests;

use App\Modules\Login\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class ClientPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === UserRole::ADMINISTRATIVO;
    }

    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'],
            'monto' => ['required', 'numeric', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D', 'min:0.01', 'max:99999999.99'],
            'medio_pago' => ['required', 'in:efectivo,transferencia,cheque,otro'],
            'datos_medio_pago' => ['nullable', 'array'],
            'datos_medio_pago.numero_cheque' => ['required_if:medio_pago,cheque', 'nullable', 'string', 'max:50'],
            'datos_medio_pago.banco_emisor' => ['required_if:medio_pago,cheque', 'nullable', 'string', 'max:100'],
            'datos_medio_pago.fecha_cobro' => ['required_if:medio_pago,cheque', 'nullable', 'date_format:Y-m-d'],
            'comprobante' => ['nullable', 'string', 'max:150'],
            'transferencia_id' => ['nullable', 'integer', 'exists:g1_transferencias,id']];
    }
}
