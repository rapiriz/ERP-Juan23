<?php

namespace App\Modules\ConciliacionBancaria\Services;

use App\Modules\ConciliacionBancaria\Models\PeriodoConciliacion;
use App\Modules\Login\Models\User;
use App\Modules\Shared\Services\G2OperationAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankPeriodService
{
    public function actor(int $id): void
    {
        $user = User::findOrFail($id);
        abort_unless($user->estado->value === 'activo' && in_array($user->rol->value, ['administrativo', 'contador'], true), 403);
    }

    public function create(string $from, string $to, int $actor): PeriodoConciliacion
    {
        $this->actor($actor);

        return DB::transaction(function () use ($from, $to, $actor) {
            // Una fila común serializa las altas de períodos incluso si aún no existe ninguno.
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            abort_if($from > $to, 422, 'El período tiene fechas invertidas.');
            abort_if(PeriodoConciliacion::where('fecha_desde', '<=', $to)->where('fecha_hasta', '>=', $from)->exists(), 409, 'El período se superpone con uno existente.');
            $period = PeriodoConciliacion::create(['fecha_desde' => $from, 'fecha_hasta' => $to, 'estado' => 'abierto', 'fecha_apertura' => now(), 'id_usuario' => $actor]);
            G2OperationAudit::record('bancos', $period->id_periodo, 'periodo_abierto', $actor, ['desde' => $from, 'hasta' => $to]);

            return $period;
        });
    }

    public function forDate(string $date, int $actor): PeriodoConciliacion
    {
        $this->actor($actor);

        return DB::transaction(function () use ($date, $actor) {
            DB::table('usuarios')->orderBy('id')->lockForUpdate()->first();
            $period = PeriodoConciliacion::where('fecha_desde', '<=', $date)->where('fecha_hasta', '>=', $date)->lockForUpdate()->first();
            if (! $period) {
                // El mes completo sólo si no invade un período manual ya existente.
                $start = Carbon::parse($date)->startOfMonth()->toDateString();
                $end = Carbon::parse($date)->endOfMonth()->toDateString();
                if (PeriodoConciliacion::where('fecha_desde', '<=', $end)->where('fecha_hasta', '>=', $start)->exists()) {
                    $start = $end = $date;
                }
                $period = $this->create($start, $end, $actor);
            }
            abort_unless($period->estado === 'abierto', 409, 'La fecha pertenece a un período cerrado.');

            return $period;
        });
    }

    public function open(int $id): PeriodoConciliacion
    {
        $period = PeriodoConciliacion::lockForUpdate()->findOrFail($id);
        abort_unless($period->estado === 'abierto', 409, 'El período está cerrado.');

        return $period;
    }
}
