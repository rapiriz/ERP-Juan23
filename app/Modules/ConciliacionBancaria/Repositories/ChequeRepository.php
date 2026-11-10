<?php

namespace App\Modules\ConciliacionBancaria\Repositories;

use App\Modules\ConciliacionBancaria\Models\Cheque;
use Illuminate\Database\Eloquent\Collection;

/**
 * Acceso a datos para el módulo de Cheques (dentro de Conciliación Bancaria).
 *
 * Las reversiones se orquestan en los servicios transaccionales, no borrando vínculos aquí.
 */
class ChequeRepository
{
    public function listar(?string $estado = null): Collection
    {
        $query = Cheque::query();

        if ($estado !== null) {
            $query->where('estado', $estado);
        }

        return $query->orderBy('fecha_cobro', 'desc')->get();
    }

    public function buscar(int $idCheque): ?Cheque
    {
        return Cheque::find($idCheque);
    }

    public function marcarDepositado(Cheque $cheque): Cheque
    {
        $cheque->estado = 'depositado';
        $cheque->fecha_deposito = now();
        $cheque->save();

        return $cheque;
    }

    public function marcarRechazado(Cheque $cheque): Cheque
    {
        $cheque->estado = 'rechazado';
        $cheque->fecha_rechazo = now();
        $cheque->save();

        return $cheque;
    }
}
