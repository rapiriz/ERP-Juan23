<?php

namespace App\Services;

use App\Repositories\ProductoLoteRepository;
use Carbon\Carbon;

class ConsultaVencimientosService
{
    private ProductoLoteRepository $repository;

    public function __construct(ProductoLoteRepository $repository)
    {
        $this->repository = $repository;
    }

    public function obtenerProximosVencer(int $dias = 30, array $filtros = [], int $pagina = 1): array
    {
        $resultado = $this->repository->obtenerProximosAVencer($dias, $pagina, 15, $filtros);

        $resultado['data'] = collect($resultado['data'])->map(function ($lote) {
            return [
                'id' => $lote->id,
                'producto' => [
                    'id' => $lote->producto->id,
                    'nombre' => $lote->producto->nombre,
                    'codigo' => $lote->producto->codigo,
                    'unidad_medida' => $lote->producto->unidad_medida ?? 'und'
                ],
                'lote' => [
                    'numero' => $lote->numero_lote,
                    'cantidad' => $lote->cantidad_actual,
                    'cantidad_inicial' => $lote->cantidad_inicial,
                    'porcentaje_consumido' => $lote->porcentaje_consumido
                ],
                'vencimiento' => [
                    'fecha' => $lote->fecha_vencimiento->format('Y-m-d'),
                    'dias_restantes' => $lote->dias_restantes,
                    'estado' => $this->determinarEstadoVencimiento($lote->dias_restantes),
                    'urgencia' => $this->obtenerNivelUrgencia($lote->dias_restantes)
                ],
                'ubicacion' => $lote->ubicacion_almacen,
                'observaciones' => $lote->observaciones
            ];
        })->toArray();

        return [
            'exito' => true,
            'datos' => $resultado['data'],
            'paginacion' => $resultado['paginacion'],
            'resumen' => $this->repository->obtenerResumenAlertas()
        ];
    }

    public function obtenerPorCriticidad(): array
    {
        $criticos = $this->repository->obtenerPorEstadoVencimiento('critico');
        $proximos = $this->repository->obtenerPorEstadoVencimiento('proximo');
        $seguros = $this->repository->obtenerPorEstadoVencimiento('seguro');

        return [
            'exito' => true,
            'critico' => [
                'total' => count($criticos),
                'cantidad' => collect($criticos)->sum('cantidad_actual'),
                'productos' => $criticos
            ],
            'proximo' => [
                'total' => count($proximos),
                'cantidad' => collect($proximos)->sum('cantidad_actual'),
                'productos' => $proximos
            ],
            'seguro' => [
                'total' => count($seguros),
                'cantidad' => collect($seguros)->sum('cantidad_actual'),
                'productos' => $seguros
            ]
        ];
    }

    public function obtenerAlertas(): array
    {
        $resumen = $this->repository->obtenerResumenAlertas();

        return [
            'exito' => true,
            'alertas' => [
                [
                    'tipo' => 'critica',
                    'titulo' => 'Productos Vencidos',
                    'cantidad' => $resumen['total_vencidos'],
                    'urgencia' => 'CRÍTICA',
                    'accion_recomendada' => 'Revisar y eliminar inmediatamente'
                ],
                [
                    'tipo' => 'alta',
                    'titulo' => 'Vencimiento en 7 días',
                    'cantidad' => $resumen['criticos_7dias'],
                    'urgencia' => 'ALTA',
                    'accion_recomendada' => 'Priorizar venta o descuento'
                ],
                [
                    'tipo' => 'media',
                    'titulo' => 'Vencimiento en 30 días',
                    'cantidad' => $resumen['proximos_30dias'],
                    'urgencia' => 'MEDIA',
                    'accion_recomendada' => 'Monitorear'
                ]
            ]
        ];
    }

    public function generarReporte(array $filtros = []): array
    {
        $todos = $this->repository->obtenerProximosAVencer(999, 1, 10000)['data'];

        $datos = collect($todos)->map(function ($lote) {
            return [
                'Código Producto' => $lote->producto->codigo,
                'Nombre Producto' => $lote->producto->nombre,
                'Lote' => $lote->numero_lote,
                'Cantidad' => $lote->cantidad_actual,
                'Fecha Vencimiento' => $lote->fecha_vencimiento->format('d/m/Y'),
                'Días Restantes' => $lote->dias_restantes,
                'Ubicación' => $lote->ubicacion_almacen,
                'Estado' => $this->determinarEstadoVencimiento($lote->dias_restantes),
                'Urgencia' => $this->obtenerNivelUrgencia($lote->dias_restantes)
            ];
        })->toArray();

        return [
            'exito' => true,
            'datos' => $datos,
            'total_registros' => count($datos),
            'fecha_generacion' => now()->format('Y-m-d H:i:s')
        ];
    }

    private function determinarEstadoVencimiento(int $diasRestantes): string
    {
        if ($diasRestantes < 0) return 'vencido';
        if ($diasRestantes <= 7) return 'crítico';
        if ($diasRestantes <= 30) return 'próximo';
        return 'seguro';
    }

    private function obtenerNivelUrgencia(int $diasRestantes): string
    {
        if ($diasRestantes < 0) return '🔴 CRÍTICA';
        if ($diasRestantes <= 7) return '🟠 ALTA';
        if ($diasRestantes <= 30) return '🟡 MEDIA';
        return '🟢 BAJA';
    }
}
