<?php

namespace App\Repositories;

use App\Models\ProductoLote;
use Carbon\Carbon;

class ProductoLoteRepository
{
    protected ProductoLote $model;

    public function __construct(ProductoLote $model)
    {
        $this->model = $model;
    }

    public function obtenerProximosAVencer(int $dias = 30, int $pagina = 1, int $porPagina = 15, array $filtros = []): array
    {
        $query = $this->model
            ->proximosAVencer($dias)
            ->with('producto');

        if (!empty($filtros['producto'])) {
            $query->whereHas('producto', function ($q) use ($filtros) {
                $q->where('nombre', 'like', "%{$filtros['producto']}%")
                  ->orWhere('codigo', 'like', "%{$filtros['producto']}%");
            });
        }

        if (!empty($filtros['ubicacion'])) {
            $query->where('ubicacion_almacen', 'like', "%{$filtros['ubicacion']}%");
        }

        $query = $query->paginate($porPagina, ['*'], 'page', $pagina);

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

    public function obtenerVencidos(int $pagina = 1, int $porPagina = 15): array
    {
        $query = $this->model
            ->vencidos()
            ->with('producto')
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

    public function obtenerPorEstadoVencimiento(string $estado): array
    {
        $ahora = now();
        $fecha7dias = $ahora->copy()->addDays(7);
        $fecha30dias = $ahora->copy()->addDays(30);

        $query = $this->model->with('producto');

        match ($estado) {
            'critico' => $query->whereBetween('fecha_vencimiento', [$ahora, $fecha7dias]),
            'proximo' => $query->whereBetween('fecha_vencimiento', [$fecha7dias, $fecha30dias]),
            'seguro' => $query->where('fecha_vencimiento', '>', $fecha30dias),
            default => $query->where('fecha_vencimiento', '>', $ahora)
        };

        return $query->where('cantidad_actual', '>', 0)->get()->toArray();
    }

    public function obtenerPorProducto(int $productoId): array
    {
        return $this->model
            ->where('producto_id', $productoId)
            ->where('cantidad_actual', '>', 0)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get()
            ->toArray();
    }

    public function crear(array $datos): ProductoLote
    {
        return $this->model->create($datos);
    }

    public function actualizar(int $id, array $datos): bool
    {
        return $this->model->find($id)->update($datos);
    }

    public function obtenerPorId(int $id): ?ProductoLote
    {
        return $this->model->with('producto')->find($id);
    }

    public function eliminar(int $id): bool
    {
        $lote = $this->model->find($id);

        if (!$lote) {
            return false;
        }

        return $lote->delete();
    }

    public function obtenerTodosPaginado(array $filtros = [], int $pagina = 1, int $porPagina = 15): array
    {
        $query = $this->model->with('producto');

        if (!empty($filtros['producto'])) {
            $query->whereHas('producto', function ($q) use ($filtros) {
                $q->where('nombre', 'like', "%{$filtros['producto']}%")
                  ->orWhere('codigo', 'like', "%{$filtros['producto']}%");
            });
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['ubicacion'])) {
            $query->where('ubicacion_almacen', 'like', "%{$filtros['ubicacion']}%");
        }

        $query = $query->orderBy('fecha_vencimiento', 'asc')
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

    public function obtenerResumenAlertas(): array
    {
        return [
            'total_vencidos' => $this->model->vencidos()->count(),
            'criticos_7dias' => $this->model->proximosAVencer(7)->count(),
            'proximos_30dias' => $this->model->proximosAVencer(30)->count(),
            'total_items_en_riesgo' => $this->model->proximosAVencer(30)->sum('cantidad_actual')
        ];
    }
}
