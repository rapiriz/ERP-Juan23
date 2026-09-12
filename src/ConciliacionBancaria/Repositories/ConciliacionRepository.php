<?php

namespace App\ConciliacionBancaria\Repositories;

use App\ConciliacionBancaria\Models\PeriodoConciliacion;
use App\ConciliacionBancaria\Models\MovimientoBancario;
use App\ConciliacionBancaria\Models\AjusteBancario;
use App\ConciliacionBancaria\Models\Cheque;
use App\ConciliacionBancaria\Models\ConciliacionDetalle;
use App\Cobros\Models\Cobro;
use App\Caja\Models\CajaMovimiento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ConciliacionRepository
{
    private const MARGEN_HORAS = 48;

    // --- PeriodoConciliacion ---

    public function buscarPeriodo(int $idPeriodo): ?PeriodoConciliacion
    {
        return PeriodoConciliacion::with('movimientosBancarios')->find($idPeriodo);
    }

    public function listarPeriodos(?string $estado = null): Collection
    {
        $query = PeriodoConciliacion::query();

        if ($estado !== null) {
            $query->where('estado', $estado);
        }

        return $query->orderBy('fecha_desde', 'desc')->get();
    }

    public function crearPeriodo(array $datos): PeriodoConciliacion
    {
        return PeriodoConciliacion::create([
            'fecha_desde' => $datos['fecha_desde'],
            'fecha_hasta' => $datos['fecha_hasta'],
            'estado' => 'abierto',
            'fecha_apertura' => now(),
            'id_usuario' => $datos['id_usuario'],
        ]);
    }

    public function cerrarPeriodo(PeriodoConciliacion $periodo): PeriodoConciliacion
    {
        $periodo->estado = 'cerrado';
        $periodo->fecha_cierre = now();
        $periodo->save();

        return $periodo;
    }

    // --- MovimientoBancario ---

    public function buscarMovimiento(int $idMovimiento): ?MovimientoBancario
    {
        return MovimientoBancario::find($idMovimiento);
    }

    public function listarMovimientosPendientes(int $idPeriodo): Collection
    {
        return MovimientoBancario::where('id_periodo', $idPeriodo)
            ->where('estado', 'pendiente')
            ->get();
    }

    public function marcarMovimientoConciliado(MovimientoBancario $movimiento): MovimientoBancario
    {
        $movimiento->estado = 'conciliado';
        $movimiento->save();

        return $movimiento;
    }

    // --- ConciliacionDetalle ---

    public function crearDetalle(array $datos): ConciliacionDetalle
    {
        return ConciliacionDetalle::create([
            'id_movimiento_bancario' => $datos['id_movimiento_bancario'],
            'id_cobro' => $datos['id_cobro'] ?? null,
            'id_movimiento_caja' => $datos['id_movimiento_caja'] ?? null,
            'id_ajuste' => $datos['id_ajuste'] ?? null,
            'id_cheque' => $datos['id_cheque'] ?? null,
            'fecha_conciliacion' => now(),
            'metodo' => $datos['metodo'],
            'id_usuario' => $datos['id_usuario'],
        ]);
    }

    // --- Busqueda de candidatos para conciliacion automatica ---

    /**
     * Candidatos para un movimiento bancario de tipo 'credito':
     * Cobro, CajaMovimiento (ingreso) y Cheque.
     *
     * Supuesto (a confirmar con el equipo): un Cobro no tiene campo propio
     * de "ya conciliado" - se considera ya usado si existe una fila en
     * CONCILIACION_DETALLE apuntandolo. Lo mismo para caja/ajuste/cheque.
     *
     * Supuesto: el monto de un Cheque es el monto total de su Cobro asociado
     * (se asume que el cobro se paga 100% con ese cheque, no combinado).
     *
     * @return array<int, array{origen: string, id: int, monto: float, fecha: mixed}>
     */
    public function buscarCandidatosCredito(MovimientoBancario $movimiento): array
    {
        $candidatos = [];

        $idsCobroYaConciliados = ConciliacionDetalle::whereNotNull('id_cobro')->pluck('id_cobro');
        $cobros = Cobro::where('monto', $movimiento->monto)
            ->whereNotIn('id_cobro', $idsCobroYaConciliados)
            ->get();
        foreach ($cobros as $cobro) {
            if ($this->dentroDelMargen($movimiento->fecha_movimiento, $cobro->fecha_cobro)) {
                $candidatos[] = ['origen' => 'cobro', 'id' => $cobro->id_cobro, 'monto' => $cobro->monto, 'fecha' => $cobro->fecha_cobro];
            }
        }

        $idsCajaYaConciliados = ConciliacionDetalle::whereNotNull('id_movimiento_caja')->pluck('id_movimiento_caja');
        $movCaja = CajaMovimiento::where('monto', $movimiento->monto)
            ->where('tipo', 'ingreso')
            ->whereNotIn('id_movimiento_caja', $idsCajaYaConciliados)
            ->get();
        foreach ($movCaja as $mc) {
            if ($this->dentroDelMargen($movimiento->fecha_movimiento, $mc->fecha)) {
                $candidatos[] = ['origen' => 'caja', 'id' => $mc->id_movimiento_caja, 'monto' => $mc->monto, 'fecha' => $mc->fecha];
            }
        }

        $idsChequeYaConciliados = ConciliacionDetalle::whereNotNull('id_cheque')->pluck('id_cheque');
        $cheques = Cheque::whereNotIn('id_cheque', $idsChequeYaConciliados)->get();
        foreach ($cheques as $cheque) {
            $montoCheque = $cheque->cobro?->monto;
            $fechaCheque = $cheque->fecha_deposito ?? $cheque->fecha_cobro;

            if ($montoCheque === null) {
                continue;
            }

            if ((float) $montoCheque === (float) $movimiento->monto
                && $this->dentroDelMargen($movimiento->fecha_movimiento, $fechaCheque)) {
                $candidatos[] = ['origen' => 'cheque', 'id' => $cheque->id_cheque, 'monto' => $montoCheque, 'fecha' => $fechaCheque];
            }
        }

        return $candidatos;
    }

    /**
     * Candidatos para un movimiento bancario de tipo 'debito':
     * AjusteBancario y CajaMovimiento (egreso).
     *
     * @return array<int, array{origen: string, id: int, monto: float, fecha: mixed}>
     */
    public function buscarCandidatosDebito(MovimientoBancario $movimiento): array
    {
        $candidatos = [];

        $idsAjusteYaConciliados = ConciliacionDetalle::whereNotNull('id_ajuste')->pluck('id_ajuste');
        $ajustes = AjusteBancario::where('monto', $movimiento->monto)
            ->whereNotIn('id_ajuste', $idsAjusteYaConciliados)
            ->get();
        foreach ($ajustes as $ajuste) {
            if ($this->dentroDelMargen($movimiento->fecha_movimiento, $ajuste->fecha_registro)) {
                $candidatos[] = ['origen' => 'ajuste', 'id' => $ajuste->id_ajuste, 'monto' => $ajuste->monto, 'fecha' => $ajuste->fecha_registro];
            }
        }

        $idsCajaYaConciliados = ConciliacionDetalle::whereNotNull('id_movimiento_caja')->pluck('id_movimiento_caja');
        $movCaja = CajaMovimiento::where('monto', $movimiento->monto)
            ->where('tipo', 'egreso')
            ->whereNotIn('id_movimiento_caja', $idsCajaYaConciliados)
            ->get();
        foreach ($movCaja as $mc) {
            if ($this->dentroDelMargen($movimiento->fecha_movimiento, $mc->fecha)) {
                $candidatos[] = ['origen' => 'caja', 'id' => $mc->id_movimiento_caja, 'monto' => $mc->monto, 'fecha' => $mc->fecha];
            }
        }

        return $candidatos;
    }

    /**
     * True si la diferencia entre las dos fechas es <= 48hs.
     */
    private function dentroDelMargen($fechaMovimiento, $fechaCandidato): bool
    {
        if ($fechaCandidato === null) {
            return false;
        }

        $diffHoras = Carbon::parse($fechaMovimiento)->diffInHours(Carbon::parse($fechaCandidato));

        return $diffHoras <= self::MARGEN_HORAS;
    }
}