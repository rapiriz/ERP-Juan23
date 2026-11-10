<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'erp:scheduler-heartbeat';

    protected $description = 'Registra el pulso del programador sin modificar datos de negocio';

    public function handle(): int
    {
        $path = storage_path('app/private/operations');
        if (! is_dir($path)) {
            mkdir($path, 0700, true);
        }
        $target = $path.'/scheduler.json';
        $temporary = tempnam($path, 'heartbeat-');
        file_put_contents($temporary, json_encode(['at' => now()->toIso8601String(), 'timestamp' => now()->timestamp], JSON_THROW_ON_ERROR), LOCK_EX);
        if (! rename($temporary, $target)) {
            @unlink($temporary);

            return self::FAILURE;
        }
        $this->info('Pulso del programador registrado.');

        return self::SUCCESS;
    }
}
