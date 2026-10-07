<?php

namespace App\Services\Gateways;

use App\Contracts\InventoryGateway;
use App\Services\ReportData;
use Illuminate\Support\Collection;

class HttpInventoryGateway implements InventoryGateway
{
    public function __construct(private ExternalJsonClient $client, private ReportData $data) {}

    public function lowStock(string $order): Collection
    {
        return $this->data->products($this->client->get('inventory', '/api/v1/productos/stock-bajo', [
            'orden' => $order,
        ]));
    }
}
