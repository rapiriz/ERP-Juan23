<?php

namespace App\ConciliacionBancaria\Controllers;

use App\ConciliacionBancaria\Models\PeriodoConciliacion;
use App\ConciliacionBancaria\Models\MovimientoBancario;
use App\ConciliacionBancaria\Models\ConciliacionDetalle;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Http\Response;
use Illuminate\Http\Request;

/**
 * Controller único para el módulo de Conciliación Bancaria.
 * Cubre los endpoints ya definidos en 02-arquitectura-tecnica.md:
 *   GET    /conciliacion
 *   GET    /conciliacion/{id}
 *   POST   /conciliacion
 *   PATCH  /conciliacion/{id}/conciliar   (cruce automático sobre el período)
 *   PATCH  /conciliacion/{id}/cerrar
 *
 * Más un endpoint propuesto, no confirmado aún con el equipo, para la
 * conciliación MANUAL de un movimiento puntual:
 *   POST   /conciliacion/movimientos/{id}/conciliar-manual
 *
 * NOTA: lógica escrita directo contra Eloquent por decisión explícita del
 * equipo para esta etapa. Pendiente migrar a Services/Repositories cuando
 * se arme esa capa (ver 03-convenciones-y-estado.md, patrón de capas).
 */
class ConciliacionController extends Controller
{
    /**
     * GET /conciliacion
     * Listar períodos de conciliación.
     */
    public function index(Request $request)
    {
        $query = PeriodoConciliacion::query();

        if ($request->has('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        $periodos = $query->orderBy('fecha_desde', 'desc')->get();

        Response::json($periodos->toArray());
    }

    /**
     * GET /conciliacion/{id}
     * Detalle de un período, con sus movimientos bancarios.
     */
    public function show(int $id)
    {
        $periodo = PeriodoConciliacion::with('movimientosBancarios')->find($id);

        if (!$periodo) {
            Response::error('Período de conciliación no encontrado', 404);
            return;
        }

        Response::json($periodo->toArray());
    }

    /**
     * POST /conciliacion
     * Crear un nuevo período de conciliación.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'id_usuario' => ['required', 'integer'],
        ]);

        $periodo = PeriodoConciliacion::create([
            'fecha_desde' => $datos['fecha_desde'],
            'fecha_hasta' => $datos['fecha_hasta'],
            'estado' => 'abierto',
            'fecha_apertura' => now(),
            'id_usuario' => $datos['id_usuario'],
        ]);

        Response::json($periodo->toArray(), 201);
    }

    /**
     * PATCH /conciliacion/{id}/conciliar
     * Dispara el cruce AUTOMÁTICO de movimientos pendientes del período.
     *
     * NOTA: acá solo se arma el esqueleto de la respuesta; el motor real de
     * comparación (monto + fecha con margen configurable, ver historia
     * "Conciliación Automática de Movimientos") todavía no está escrito.
     * Cuando exista el Service correspondiente, este método debería quedar
     * reducido a una sola llamada, sin lógica de negocio acá.
     */
    public function conciliarAutomatico(int $id)
    {
        $periodo = PeriodoConciliacion::find($id);

        if (!$periodo) {
            Response::error('Período de conciliación no encontrado', 404);
            return;
        }

        if ($periodo->estado !== 'abierto') {
            Response::error('Solo se puede conciliar un período abierto', 400);
            return;
        }

        $movimientosPendientes = MovimientoBancario::where('id_periodo', $id)
            ->where('estado', 'pendiente')
            ->get();

        // TODO: implementar el cruce real (monto + fecha con margen +
        // tipo de movimiento) contra Cobro/CajaMovimiento/AjusteBancario/Cheque.
        // Placeholder: no concilia nada todavía, solo devuelve lo pendiente.

        Response::json([
            'periodo' => $periodo->toArray(),
            'pendientes_encontrados' => $movimientosPendientes->count(),
            'conciliados_automaticamente' => 0,
        ]);
    }

    /**
     * POST /conciliacion/movimientos/{id}/conciliar-manual
     * PROPUESTO — no confirmado aún con el equipo.
     * Asocia manualmente un movimiento bancario con uno o varios registros
     * del sistema (cobro, movimiento de caja, ajuste o cheque).
     *
     * Ver historia "Conciliación Manual de Transacciones": debe validar que
     * la suma de los registros del sistema coincida exactamente con el
     * monto del movimiento bancario antes de guardar.
     */
    public function conciliarManual(Request $request, int $id)
    {
        $movimiento = MovimientoBancario::find($id);

        if (!$movimiento) {
            Response::error('Movimiento bancario no encontrado', 404);
            return;
        }

        $datos = $request->validate([
            'id_cobro' => ['nullable', 'integer'],
            'id_movimiento_caja' => ['nullable', 'integer'],
            'id_ajuste' => ['nullable', 'integer'],
            'id_cheque' => ['nullable', 'integer'],
            'id_usuario' => ['required', 'integer'],
        ]);

        $origenes = array_filter([
            $datos['id_cobro'] ?? null,
            $datos['id_movimiento_caja'] ?? null,
            $datos['id_ajuste'] ?? null,
            $datos['id_cheque'] ?? null,
        ]);

        if (count($origenes) !== 1) {
            Response::error('Debe indicarse exactamente un origen (cobro, movimiento de caja, ajuste o cheque)', 400);
            return;
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

        Response::json($detalle->toArray(), 201);
    }

    /**
     * PATCH /conciliacion/{id}/cerrar
     * Cierra el período de conciliación.
     */
    public function cerrar(int $id)
    {
        $periodo = PeriodoConciliacion::find($id);

        if (!$periodo) {
            Response::error('Período de conciliación no encontrado', 404);
            return;
        }

        if ($periodo->estado !== 'abierto') {
            Response::error('El período ya está cerrado', 400);
            return;
        }

        $periodo->estado = 'cerrado';
        $periodo->fecha_cierre = now();
        $periodo->save();

        Response::json($periodo->toArray());
    }
}