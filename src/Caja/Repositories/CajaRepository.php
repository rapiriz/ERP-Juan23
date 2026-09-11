<?php

namespace App\Caja\Repositories;

use App\Caja\Models\Caja;
use App\Caja\Models\CajaMovimiento;
use Illuminate\Database\Eloquent\Collection;

class CajaRepository
{
    public function buscarPorId(int $idCaja): ?Caja
    {
        return Caja::find($idCaja);
    }

    public function buscarAbiertaDeUsuario(int $idUsuario, string $fecha): ?Caja
    {
        return Caja::where('id_usuario', $idUsuario)
            ->where('fecha', $fecha)
            ->where('estado', Caja::ESTADO_ABIERTA)
            ->first();
    }

    public function buscarPorUsuarioYFecha(int $idUsuario, string $fecha): ?Caja
    {
        return Caja::where('id_usuario', $idUsuario)
            ->where('fecha', $fecha)
            ->first();
    }

    public function listarPorFecha(string $fecha): Collection
    {
        return Caja::where('fecha', $fecha)->get();
    }

    public function crear(array $datos): Caja
    {
        return Caja::create($datos);
    }

    public function guardar(Caja $caja): Caja
    {
        $caja->save();
        return $caja;
    }

    /**
     * Historial de cierres. $idUsuario opcional: si viene, filtra solo los
     * cierres de ese usuario; si no, trae todos (vista admin).
     */
    public function listarCierres(?int $idUsuario = null): Collection
    {
        $query = Caja::where('estado', Caja::ESTADO_CERRADA);

        if ($idUsuario !== null) {
            $query->where('id_usuario', $idUsuario);
        }

        return $query->orderByDesc('fecha_hora_cierre')->get();
    }

    public function buscarCierrePorId(int $idCaja): ?Caja
    {
        return Caja::where('id_caja', $idCaja)
            ->where('estado', Caja::ESTADO_CERRADA)
            ->first();
    }

    public function crearMovimiento(array $datos): CajaMovimiento
    {
        return CajaMovimiento::create($datos);
    }

    public function buscarMovimientoPorId(int $idMovimiento): ?CajaMovimiento
    {
        return CajaMovimiento::find($idMovimiento);
    }

    public function guardarMovimiento(CajaMovimiento $movimiento): CajaMovimiento
    {
        $movimiento->save();
        return $movimiento;
    }

    public function listarMovimientosDeCaja(int $idCaja): Collection
    {
        return CajaMovimiento::where('id_caja', $idCaja)->get();
    }

    /**
     * Suma neta de movimientos de una caja (ingresos - egresos), usada para
     * calcular el saldo esperado al cerrar.
     */
    public function calcularSaldoMovimientos(int $idCaja): float
    {
        return (float) $this->listarMovimientosDeCaja($idCaja)
            ->sum(fn (CajaMovimiento $m) => $m->esIngreso() ? (float) $m->monto : -1 * (float) $m->monto);
    }
}
