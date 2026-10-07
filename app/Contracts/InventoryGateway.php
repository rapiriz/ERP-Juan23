<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface InventoryGateway
{
    public function lowStock(string $order): Collection;
}
