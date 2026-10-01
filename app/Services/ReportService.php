<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class ReportService
{
    public function dailySales(CarbonInterface $date): Collection
    {
        return Venta::query()
            ->with('cliente:id,nombre,apellido_razon_social')
            ->whereDate('fecha', $date->toDateString())
            ->whereIn('estado', [
                SaleStatus::CONFIRMADA->value,
                SaleStatus::PAGADA->value,
                SaleStatus::FACTURADA->value,
            ])
            ->orderBy('fecha')
            ->get();
    }

    public function lowStock(string $order = 'asc'): Collection
    {
        $direction = $order === 'desc' ? 'desc' : 'asc';

        return Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre'])
            ->whereColumn('stock', '<', 'stock_minimo')
            ->orderBy('stock', $direction)
            ->orderBy('nombre')
            ->get();
    }

    public function clientSales(Cliente $cliente, ?string $from = null, ?string $to = null): Collection
    {
        return $cliente->ventas()
            ->with(['detalles.producto:id,codigo,nombre', 'usuario:id,nombre'])
            ->when($from, fn ($query) => $query->whereDate('fecha', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('fecha', '<=', $to))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }
}
