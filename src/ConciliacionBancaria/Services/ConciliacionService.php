<?php

namespace App\ConciliacionBancaria\Services;

use App\ConciliacionBancaria\Models\PeriodoConciliacion;
use App\ConciliacionBancaria\Models\MovimientoBancario;
use App\ConciliacionBancaria\Models\ConciliacionDetalle;

/**
 * Contiene toda la lógica de negocio del módulo de Conciliación Bancaria.
 * El Controller solo valida el request y traduce estos resultados a HTTP.
 *
 * Convención de retorno (opción B, decidida por el equipo para un proyecto
 * grande/heterogéneo): nunca se lanzan excepciones propias. Cada método
 * devuelve:
 *   - un array con los datos, si salió bien
 *   - null, si el registro buscado no existe
 *   - ['error' => true, 'mensaje' => '...'] si la operación es inválida
 *     por una regla de negocio (no por "no encontrado")
 *
 * El Controller distingue los tres casos así:
 *   $resultado = $service->metodo(...);
 *   if ($resultado === null) { Response::error('...', 404); return; }
 *   if (is_array($resultado) && ($resultado['error'] ?? false)) {
 *       Response::error($resultado['mensaje'], 400); return;
 *   }
 *   Response::json($resultado, 200|201);
 */
class ConciliacionService
{
    /**
     * Lista períodos de conciliación, opcionalmente filtrados por estado.
     */
    public function listarPeriodos(?string $estado = null): array
    {
        $query = PeriodoConciliacion::query();

        if ($estado !== null) {
            $query->where('estado', $estado);
        }

        return $query->orderBy('fecha_desde', 'desc')->get()->toArray();
    }

    /**
     * Detalle de un período, con sus movimientos bancarios.
     * Devuelve null si no existe.
     */
    public function buscarPeriodo(int $idPeriodo): ?array
    {
        $periodo = PeriodoConciliacion::with('movimientosBancarios')->find($idPeriodo);

        return $periodo?->toArray();
    }

    /**
     * Crea un nuevo período de conciliación en estado 'abierto'.
     * $datos ya viene validado por el Controller (fecha_desde, fecha_hasta, id_usuario).
     */
    public function crearPeriodo(array $datos): array
    {
        $periodo = PeriodoConciliacion::create([
            'fecha_desde' => $datos['fecha_desde'],
            'fecha_hasta' => $datos['fecha_hasta'],
            'estado' => 'abierto',
            'fecha_apertura' => now(),
            'id_usuario' => $datos['id_usuario'],
        ]);

        return $periodo->toArray();
    }

    /**
     * Dispara el cruce AUTOMÁTICO de movimientos pendientes de un período.
     *
     * Devuelve null si el período no existe.
     * Devuelve ['error' => true, 'mensaje' => ...] si el período no está abierto.
     *
     * NOTA: el motor real de comparación (monto + fecha con margen configurable,
     * ver historia "Conciliación Automática de Movimientos") todavía no está
     * implementado. Por ahora solo cuenta los pendientes — placeholder explícito,
     * a completar como parte de esa historia.
     */
    public function conciliarAutomatico(int $idPeriodo): ?array
    {
        $periodo = PeriodoConciliacion::find($idPeriodo);

        if (!$periodo) {
            return null;
        }

        if ($periodo->estado !== 'abierto') {
            return ['error' => true, 'mensaje' => 'Solo se puede conciliar un período abierto'];
        }

        $movimientosPendientes = MovimientoBancario::where('id_periodo', $idPeriodo)
            ->where('estado', 'pendiente')
            ->get();

        // TODO: implementar el cruce real (monto + fecha con margen +
        // tipo de movimiento) contra Cobro/CajaMovimiento/AjusteBancario/Cheque.
        // Placeholder: no concilia nada todavía, solo devuelve lo pendiente.

        return [
            'periodo' => $periodo->toArray(),
            'pendientes_encontrados' => $movimientosPendientes->count(),
            'conciliados_automaticamente' => 0,
        ];
    }

    /**
     * Asocia manualmente un movimiento bancario con un único origen
     * (cobro, movimiento de caja, ajuste o cheque).
     *
     * Devuelve null si el movimiento bancario no existe.
     * Devuelve ['error' => true, 'mensaje' => ...] si no se cumple la regla
     * de "exactamente un origen".
     *
     * $datos ya viene validado por el Controller (los 4 ids son nullable,
     * id_usuario es requerido).
     */
    public function conciliarManual(int $idMovimiento, array $datos): ?array
    {
        $movimiento = MovimientoBancario::find($idMovimiento);

        if (!$movimiento) {
            return null;
        }

        $origenes = array_filter([
            $datos['id_cobro'] ?? null,
            $datos['id_movimiento_caja'] ?? null,
            $datos['id_ajuste'] ?? null,
            $datos['id_cheque'] ?? null,
        ]);

        if (count($origenes) !== 1) {
            return [
                'error' => true,
                'mensaje' => 'Debe indicarse exactamente un origen (cobro, movimiento de caja, ajuste o cheque)',
            ];
        }

        // TODO: validar que el monto del origen coincida exactamente con
        // $movimiento->monto antes de guardar (ver historia de Conciliación Manual).

        $detalle = ConciliacionDetalle::create([
            'id_movimiento_bancario' => $movimiento->id_movimiento_bancario,
            'id_cobro' => $datos['id_cobro'] ?? null,
            'id_movimiento_caja' => $datos['id_movimiento_caja'] ?? null,
            'id_ajuste' => $datos['id_ajuste'] ?? null,
            'id_cheque' => $datos['id_cheque'] ?? null,
            'fecha_conciliacion' => now(),
            'metodo' => 'manual',
            'id_usuario' => $datos['id_usuario'],
        ]);

        $movimiento->estado = 'conciliado';
        $movimiento->save();

        return $detalle->toArray();
    }

    /**
     * Cierra un período de conciliación.
     *
     * Devuelve null si el período no existe.
     * Devuelve ['error' => true, 'mensaje' => ...] si ya estaba cerrado.
     */
    public function cerrarPeriodo(int $idPeriodo): ?array
    {
        $periodo = PeriodoConciliacion::find($idPeriodo);

        if (!$periodo) {
            return null;
        }

        if ($periodo->estado !== 'abierto') {
            return ['error' => true, 'mensaje' => 'El período ya está cerrado'];
        }

        $periodo->estado = 'cerrado';
        $periodo->fecha_cierre = now();
        $periodo->save();

        return $periodo->toArray();
    }
}