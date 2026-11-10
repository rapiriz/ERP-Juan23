<?php

/** Punto de entrada de composer setup, conservando la clave de instalaciones existentes. */
require __DIR__.'/vendor/autoload.php';

use App\Services\SchemaCaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app(SchemaCaseGuard::class)->conflicts()) {
    fwrite(STDERR, "Hay tablas con mayúsculas/minúsculas incompatibles. Respaldar y revisar el inventario antes de migrar; no se renombra ni borra nada automáticamente.\n");
    exit(1);
}

foreach ([
    blank(config('app.key')) ? ['key:generate', '--force'] : null,
    ['migrate', '--force'],
    ['erp:configure'],
] as $arguments) {
    if ($arguments === null) {
        continue;
    }
    $process = new Process([PHP_BINARY, __DIR__.'/artisan', ...$arguments], __DIR__);
    $process->setTimeout(null);
    $process->run(fn ($type, $buffer) => fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer));
    if (! $process->isSuccessful()) {
        exit($process->getExitCode() ?: 1);
    }
}
