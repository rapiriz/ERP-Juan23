<?php

namespace App\Modules\ConciliacionBancaria\Services;

use App\Modules\ConciliacionBancaria\Models\MovimientoBancario;
use App\Modules\ConciliacionBancaria\Repositories\ConciliacionRepository;
use Exception;

class ConciliacionService
{
    private ConciliacionRepository $repository;

    public function __construct(ConciliacionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * HU-02: Obtener propuestas de auto-matching.
     */
    public function obtenerSugerencias(): array
    {
        return $this->repository->obtenerPropuestasAutoMatching();
    }

    /**
     * HU-02: Ejecutar auto-matching de todas las propuestas de alta confianza.
     */
    public function ejecutarAutoMatching(): array
    {
        $propuestas = $this->repository->obtenerPropuestasAutoMatching();
        $conciliados = [];

        foreach ($propuestas['alta_confianza'] as $item) {
            $mov = $item['movimiento'];
            $cobro = $item['cobro'];

            $res = $this->repository->conciliarMovimientoConCobro(
                $mov->id_movimiento_bancario,
                $cobro->id_rendicion_cobro,
                'Conciliado automáticamente por Auto-Matching (monto y fecha coincidentes)'
            );
            $conciliados[] = $res;
        }

        return [
            'total_procesados' => count($conciliados),
            'conciliados' => $conciliados,
        ];
    }

    /**
     * HU-02: Conciliación manual entre un movimiento y un cobro seleccionado.
     */
    public function conciliarManual(int $idMovimiento, int $idRendicionCobro, ?string $observaciones = null): MovimientoBancario
    {
        return $this->repository->conciliarMovimientoConCobro($idMovimiento, $idRendicionCobro, $observaciones);
    }

    /**
     * HU-03: Gestión de excepciones y estados sin tablas adicionales.
     */
    public function gestionarExcepcion(int $idMovimiento, string $estado, ?string $motivo = null): MovimientoBancario
    {
        $estadosValidos = ['en_disputa', 'revision_manual', 'no_aplica', 'cerrado_definitivo', 'pendiente'];
        if (!in_array($estado, $estadosValidos)) {
            throw new Exception("Estado de conciliación inválido: {$estado}");
        }

        return $this->repository->cambiarEstadoMovimiento($idMovimiento, $estado, $motivo);
    }
}
