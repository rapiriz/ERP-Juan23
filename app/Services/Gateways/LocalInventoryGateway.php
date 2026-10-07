<?php

namespace App\Services\Gateways;

use App\Contracts\InventoryGateway;
use App\Models\Producto;
use App\Services\ReportData;
use Illuminate\Support\Collection;

class LocalInventoryGateway implements InventoryGateway
{
    public function __construct(private ReportData $data) {}

    public function lowStock(string $order): Collection
    {
        $products = Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre'])
            ->whereColumn('stock', '<', 'stock_minimo')
            ->orderBy('stock', $order === 'desc' ? 'desc' : 'asc')
            ->orderBy('nombre')->get();

        return $this->data->products($products->map(fn (Producto $product): array => [
            'codigo' => $product->codigo,
            'nombre' => $product->nombre,
            'stock' => $product->stock,
            'stock_minimo' => $product->stock_minimo,
            'categoria' => $product->categoria ? ['nombre' => $product->categoria->nombre] : null,
            'marca' => $product->marca ? ['nombre' => $product->marca->nombre] : null,
        ])->all());
    }
}
