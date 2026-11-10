<?php

namespace App\Console\Commands;

use App\Services\DeploymentReadiness;
use App\Services\SchemaCaseGuard;
use Illuminate\Console\Command;

class DeploymentDoctor extends Command
{
    protected $signature = 'erp:doctor {--production} {--database} {--json}';

    protected $description = 'Comprueba preparación de despliegue sin publicar secretos ni enviar correos';

    public function handle(DeploymentReadiness $readiness): int
    {
        $checks = $readiness->checks((bool) $this->option('production'));
        if ($this->option('database')) {
            try {
                $checks['tablas_case_compatibles'] = ! app(SchemaCaseGuard::class)->conflicts();
            } catch (\Throwable) {
                $checks['tablas_case_compatibles'] = false;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $key => $ok) {
                $this->line(($ok ? 'OK ' : 'PENDIENTE ').$key);
            }
        }
        $this->comment('La configuración no certifica entrega SMTP ni el certificado/proxy HTTPS: comprobarlos en el servidor de destino.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
