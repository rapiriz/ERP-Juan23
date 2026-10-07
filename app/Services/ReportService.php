<?php

namespace App\Services;

use App\Contracts\InventoryGateway;
use App\Contracts\SalesGateway;
use App\Models\Cliente;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(private SalesGateway $sales, private InventoryGateway $inventory) {}

    public function dailySales(CarbonInterface $date): Collection
    {
        return $this->sales->dailySales($date);
    }

    public function lowStock(string $order = 'asc'): Collection
    {
        return $this->inventory->lowStock($order);
    }

    public function clientSales(Cliente $cliente, ?string $from = null, ?string $to = null): Collection
    {
        return $this->sales->clientHistory($cliente->id, $from, $to);
    }
}
