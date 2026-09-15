<?php

namespace App\Services;

use App\Repositories\SugerenciaCompraRepository;
use App\Repositories\ProductoLoteRepository;

class DashboardReposicionService
{
    private SugerenciaCompraRepository $sugerenciaRepo;
    private ProductoLoteRepository $loteRepo;

    public function __construct(
        SugerenciaCompraRepository $sugerenciaRepo,
        ProductoLoteRepository $loteRepo
    ) {
        $this->sugerenciaRepo = $sugerenciaRepo;
        $this->loteRepo = $loteRepo;
    }

    public function obtenerResumenGeneral(): array
    {
        $resumenSugerencias = $this->sugerenciaRepo->obtenerResumen();
        $resumenVencimientos = $this->loteRepo->obtenerResumenAlertas();

        return [
            'exito' => true,
            'sugerencias' => $resumenSugerencias,
            'vencimientos' => $resumenVencimientos
        ];
    }

    public function obtenerDatosGraficoMotivos(): array
    {
        $datos = $this->sugerenciaRepo->obtenerEstadisticasPorMotivo();

        $etiquetas = [
            'bajo_stock' => 'Stock Bajo',
            'proximo_vencer' => 'Próximo a Vencer',
            'agotamiento_inmediato' => 'Agotamiento Inmediato'
        ];

        return [
            'exito' => true,
            'datos' => collect($datos)->map(function ($fila) use ($etiquetas) {
                return [
                    'motivo' => $etiquetas[$fila['motivo_generacion']] ?? $fila['motivo_generacion'],
                    'total' => (int) $fila['total'],
                    'cantidad' => (int) $fila['cantidad'],
                    'costo' => round((float) $fila['costo'], 2)
                ];
            })->toArray()
        ];
    }

    public function obtenerDatosGraficoTendencia(int $dias = 30): array
    {
        $datos = $this->sugerenciaRepo->obtenerTendenciaUltimosDias($dias);

        return [
            'exito' => true,
            'datos' => collect($datos)->map(function ($fila) {
                return [
                    'fecha' => $fila['fecha'],
                    'total' => (int) $fila['total'],
                    'costo' => round((float) $fila['costo'], 2)
                ];
            })->toArray()
        ];
    }

    public function obtenerDatosGraficoCriticidad(): array
    {
        $resumen = $this->loteRepo->obtenerResumenAlertas();

        return [
            'exito' => true,
            'datos' => [
                ['categoria' => 'Vencidos', 'total' => $resumen['total_vencidos']],
                ['categoria' => 'Críticos (7 días)', 'total' => $resumen['criticos_7dias']],
                ['categoria' => 'Próximos (30 días)', 'total' => $resumen['proximos_30dias']]
            ]
        ];
    }
}
