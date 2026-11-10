<?php

namespace App\Modules\ConciliacionBancaria\Repositories;

use App\Modules\ConciliacionBancaria\Models\PeriodoConciliacion;
use Illuminate\Database\Eloquent\Collection;

/** Las mutaciones se concentran en BankPeriodService y BankReconciliationService. */
class ConciliacionRepository
{
    public function buscarPeriodo(int $id): ?PeriodoConciliacion
    {
        return PeriodoConciliacion::with('movimientosBancarios')->find($id);
    }

    public function listarPeriodos(?string $state = null): Collection
    {
        return PeriodoConciliacion::when($state, fn ($q) => $q->where('estado', $state))->orderByDesc('fecha_desde')->get();
    }
}
