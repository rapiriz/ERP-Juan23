<?php

/** Actualización aditiva de una base QA existente; nunca usa el destino de .env. */
require __DIR__.'/../vendor/autoload.php';
$name = getenv('ERP_G3_G4_TEST_DATABASE');
if (! is_string($name) || ! preg_match('/^erp_(qa|test)_[a-z0-9_]+$/D', $name)) {
    throw new RuntimeException('Definir una base exclusiva ERP_G3_G4_TEST_DATABASE=erp_qa_... en 3315.');
}
putenv('APP_ENV=testing');
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 3315,
    'database.connections.mysql.database' => $name, 'database.connections.mysql.username' => getenv('ERP_TEST_DB_USERNAME') ?: 'root',
    'database.connections.mysql.password' => getenv('ERP_TEST_DB_PASSWORD') ?: '', 'database.connections.mysql.url' => null]);
Illuminate\Support\Facades\DB::purge('mysql');
Tests\TestDatabaseSafety::check(Illuminate\Support\Facades\DB::connection());
$code = Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo Illuminate\Support\Facades\Artisan::output();
exit($code);
