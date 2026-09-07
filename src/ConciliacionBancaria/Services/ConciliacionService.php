<?php

namespace App\ConciliacionBancaria\Services;

use App\ConciliacionBancaria\Repositories\ConciliacionRepository;

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

    /**
     * Motor de conciliación automática. Reglas acordadas con el equipo:
     *   - Margen de fecha: hasta 48hs entre el movimiento bancario y el
     *     registro candidato.
     *   - Monto: coincidencia exacta, sin tolerancia.
     *   - Relación: siempre 1 a 1.
     *   - Ambigüedad: si hay más de un