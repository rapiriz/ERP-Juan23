<?php

namespace App\ConciliacionBancaria\Services;

use App\ConciliacionBancaria\Repositories\ConciliacionRepository;

/**
 * Lógica de negocio del módulo de Conciliación Bancaria.
 * Ya no accede a los Models directamente: todo pasa por ConciliacionRepository.
 *
 * Convención de retorno (opción B, sin excepciones propias):
 *   - array con los datos, si salió bien
 *   - null, si el registro buscado no existe
 *   - ['error' => true, 'mensaje' => '...'] si la operación es inválida
 *     por una regla de negocio
 */
class ConciliacionService
{
    public function __construct(private ConciliacionRepository $repository)
    {
    }

    public function listarPeriodos(?string $estado = null): array
    {
        return $this->repository->listarPeriodos($estado)->toArray();
    }

    public function buscarPeriodo(int $idPeriodo): ?array
    {
        $periodo = $this->repository->buscarPeriodo($idPeriodo);

        return $periodo?->toArray();
    }

    public function crearPeriodo(array $datos): array
    {
        $periodo = $this->repository->crearPeriodo($datos);

        return $periodo->toArray();
    }

    public function conciliarAutomatico(int $idPeriodo): ?array
    {
        $periodo = $this->repository->buscarPeriodo($idPeriodo);

        if (!$periodo) {
            return null;
        }

        if ($periodo->estado !== 'abierto') {
            return ['error' => true, 'mensaje' => 'Solo se puede conciliar un período abierto'];
        }

        $movimientosPendientes = $this->repository->listarMovimientosPendientes($idPeriodo);

        // TODO: implementar el cruce real (monto + fecha con margen +
        // tipo de movimiento) contra Cobro/CajaMovimiento/AjusteBancario/Cheque.
        // Placeholder: no concilia nada todavía, solo devuelve lo pendiente.

        return [
            'periodo' => $periodo->toArray(),
            'pendientes_encontrados' => $movimientosPendientes->count(),
            'conciliados_automaticamente' => 0,
        ];
    }

    public function conciliarManual(int $idMovimiento, array $datos): ?array
    {
        $movimiento = $this->repository->buscarMovimiento($idMovimiento);

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

        $detalle = $this->repository->crearDetalle([
            'id_movimiento_bancario' => $movimiento->id_movimiento_bancario,
            'id_cobro' => $datos['id_cobro'] ?? null,
            'id_movimiento_caja' => $datos['id_movimiento_caja'] ?? null,
            'id_ajuste' => $datos['id_ajuste'] ?? null,
            'id_cheque' => $datos['id_cheque'] ?? null,
            'metodo' => 'manual',
            'id_usuario' => $datos['id_usuario'],
        ]);

        $this->repository->marcarMovimientoConciliado($movimiento);

        return $detalle->toArray();
    }

    public function cerrarPeriodo(int $idPeriodo): ?array
    {
        $periodo = $this->repository->buscarPeriodo($idPeriodo);

        if (!$periodo) {
            return null;
        }

        if ($periodo->estado !== 'abierto') {
            return ['error' => true, 'mensaje' => 'El período ya está cerrado'];
        }

        $periodo = $this->repository->cerrarPeriodo($periodo);

        return $periodo->toArray();
    }
}