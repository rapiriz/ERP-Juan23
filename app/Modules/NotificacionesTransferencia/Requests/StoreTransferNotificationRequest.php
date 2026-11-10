<?php

namespace App\Modules\NotificacionesTransferencia\Requests;

use App\Modules\Login\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === UserRole::ADMINISTRATIVO;
    }

    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'monto' => ['required', 'numeric', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/D', 'min:0.01', 'max:99999999.99'],
            'referencia' => ['required', 'string', 'max:150'],
            'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120']];
    }
}
