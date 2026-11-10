<?php

namespace App\Console\Commands;

use App\Modules\Ventas\Services\StockDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetrySalesStock extends Command
{
    protected $signature = 'ventas:reenviar-stock {--limit=100}';

    protected $description = 'Reintenta ajustes durables de stock con su misma clave idempotente';

    public function handle(StockDeliveryService $delivery): int
    {
        $ids = DB::table('g4_stock_operations')->where('estado', 'pendiente')->orderBy('id')
            ->limit(max(1, min(1000, (int) $this->option('limit'))))->pluck('id');
        $failed = 0;
        foreach ($ids as $id) {
            $ok = $delivery->deliver((int) $id);
            $this->line('#'.$id.($ok ? ' aplicado' : ' pendiente; consultar ultimo_error'));
            $failed += $ok ? 0 : 1;
        }
        $this->info(count($ids).' operaciones revisadas; '.$failed.' pendientes.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
