<?php

namespace App\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SalesGateway
{
    public function dailySales(CarbonInterface $date): Collection;

    public function clientHistory(int $clientId, ?string $from, ?string $to): Collection;
}
