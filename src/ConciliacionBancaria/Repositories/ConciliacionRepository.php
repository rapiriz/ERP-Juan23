<?php

namespace App\ConciliacionBancaria\Repositories;

use App\ConciliacionBancaria\Models\PeriodoConciliacion;
use App\ConciliacionBancaria\Models\MovimientoBancario;
use App\ConciliacionBancaria\Models\ConciliacionDetalle;
use Illuminate\Database\Eloquent\Collection;

/**
 * Encapsula todo el acceso a datos (Eloquent) del módulo de Conciliación
 * Bancaria. El Service llama a este Repository en vez de usar los Models
 * directamente — así, si el día de mañana cambia la forma de consultar
 * (por ejemplo, se agrega caché, o se cambia algún criterio de búsqueda),
 * se toca solo este archivo y no el Service.
 *
 * Devuelve directamente instancias/colecciones de Eloquent (no arrays) —
 * es el Service quien decide cuándo convertir a array para la respuesta.
 */
class ConciliacionRepository
{
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
}