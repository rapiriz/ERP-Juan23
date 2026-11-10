<?php

namespace App\Console\Commands;

use App\Modules\Shared\Services\FinancialIntegrityReview;
use Illuminate\Console\Command;

class AuditFinancialHistory extends Command
{
    protected $signature = 'erp:audit-finanzas {--json}';

    protected $description = 'Revisa vínculos históricos sin escribir ni aplicar cobros';

    public function handle(FinancialIntegrityReview $review): int
    {
        $findings = $review->findings();
        if ($this->option('json')) {
            $this->line(json_encode($findings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($findings as $key => $item) {
                $this->line($key.': '.$item['total']);
            }
        }

        return array_sum(array_column($findings, 'total')) ? self::FAILURE : self::SUCCESS;
    }
}
