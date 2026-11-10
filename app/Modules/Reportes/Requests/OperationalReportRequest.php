<?php

namespace App\Modules\Reportes\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperationalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['fecha' => ['nullable', 'date_format:Y-m-d'], 'dias' => ['nullable', 'in:7,15,30'],
            'desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'producto_id' => ['nullable', 'integer', 'min:1'],
            'orden' => ['nullable', $this->route('report') === 'debtors' ? 'in:mayor,menor' : 'in:asc,desc']];
    }

    public function filters(): array
    {
        return [...array_filter($this->validated(), fn ($value) => $value !== null && $value !== ''),
            'fecha' => $this->validated('fecha') ?: now()->timezone(config('app.timezone'))->toDateString(),
            'dias' => (int) ($this->validated('dias') ?: 7)];
    }
}
