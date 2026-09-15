<?php

namespace App\Repositories;

use App\Models\SugerenciaCompra;

class SugerenciaCompraRepository
{
    protected SugerenciaCompra $model;

    public function __construct(SugerenciaCompra $model)
    {
        $this->model = $model;
    }

    public function crear(array $datos): SugerenciaCompra
    {
        return $this->model->create($datos);
    }

    public function obtenerPendientes(int $pagina = 1, int $porPagina = 15): array
    {
        $query = $this->model
            ->pendientes()
            ->with(['producto', 'proveedor'])
            ->orderBy('fecha_reorden', 'asc')
            ->paginate($porPagina, ['*'], 'page', $pagina);

        return [
            'data' => $query->items(),
            'paginacion' => [
                'total' => $query->total(),
                'por_pagina' => $query->perPage(),
                'pagina_actual' => $query->currentPage(),
                'total_paginas' => $query->lastPage(),
                'tiene_siguiente' => $query->hasMorePages()
            ]
        ];
    }

    public function obtenerPorProducto(int $productoId): array
    {
        return $this->model
            ->where('producto_id', $productoId)
            ->with(['producto', 'proveedor'])
            ->orderBy('fecha_reorden', 'desc')
            ->get()
            ->toArray();
    }

    public function actualizar(int $id, array $datos): bool
    {
        return $this->model->find($id)->update($datos);
    }

    public function marcarProcesada(int $id): bool
    {
        return $this->actualizar($id, ['estado' => 'procesada']);
    }

    public function marcarRechazada(int $id): bool
    {
        return $this->actualizar($id, ['estado' => 'rechazada']);
    }

    public function obtenerPorId(int $id): ?SugerenciaCompra
    {
        return $this->model->with(['producto', 'proveedor'])->find($id);
    }

    public function procesarMultiples(array $ids): array
    {
        $procesadas = 0;
        $fallidas = [];

        foreach ($ids as $id) {
            try {
                if ($this->marcarProcesada((int) $id)) {
                    $procesadas++;
                } else {
                    $fallidas[] = $id;
                }
            } catch (\Exception $e) {
                $fallidas[] = $id;
            }
        }

        return ['procesadas' => $procesadas, 'fallidas' => $fallidas];
    }

    public function obtenerConFiltros(array $filtros = [], int $pagina = 1, int $porPagina = 15): array
    {
        $query = $this->model->with(['producto', 'proveedor']);

        if (!empty($filtros['motivo'])) {
            $query->where('motivo_generacion', $filtros['motivo']);
        }

        $query->where('estado', $filtros['estado'] ?? 'pendiente');

        $query = $query->orderBy('fecha_reorden', 'asc')
            ->paginate($porPagina, ['*'], 'page', $pagina);

        return [
            'data' => $query->items(),
            'paginacion' => [
                'total' => $query->total(),
                'por_pagina' => $query->perPage(),
                'pagina_actual' => $query->currentPage(),
                'total_paginas' => $query->lastPage(),
                'tiene_siguiente' => $query->hasMorePages()
            ]
        ];
    }

    public function obtenerEstadisticasPorMotivo(): array
    {
        return $this->model
            ->pendientes()
            ->selectRaw('motivo_generacion, count(*) as total, sum(cantidad_sugerida) as cantidad, sum(costo_total) as costo')
            ->groupBy('motivo_generacion')
            ->get()
            ->toArray();
    }

    public function obtenerTendenciaUltimosDias(int $dias = 30): array
    {
        return $this->model
            ->selectRaw('DATE(created_at) as fecha, count(*) as total, sum(costo_total) as costo')
            ->where('created_at', '>=', now()->subDays($dias))
            ->groupBy('fecha')
            ->orderBy('fecha', 'asc')
            ->get()
            ->toArray();
    }

    public function obtenerResumen(): array
    {
        return [
            'total_pendientes' => $this->model->pendientes()->count(),
            'costo_total_sugerido' => $this->model->pendientes()->sum('costo_total'),
            'cantidad_total' => $this->model->pendientes()->sum('cantidad_sugerida')
        ];
    }

    public function obtenerTodos(int $pagina = 1, int $porPagina = 15): array
    {
        $query = $this->model
            ->with(['producto', 'proveedor'])
            ->orderBy('fecha_reorden', 'asc')
            ->paginate($porPagina, ['*'], 'page', $pagina);

        return [
            'data' => $query->items(),
            'paginacion' => [
                'total' => $query->total(),
                'por_pagina' => $query->perPage(),
                'pagina_actual' => $query->currentPage(),
                'total_paginas' => $query->lastPage(),
                'tiene_siguiente' => $query->hasMorePages()
            ]
        ];
    }
}
