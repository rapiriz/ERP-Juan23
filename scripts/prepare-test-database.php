<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Crea una base vacía exclusiva de QA. Nunca usa .env para elegir el destino. */
require __DIR__.'/../vendor/autoload.php';
$name = getenv('ERP_G3_G4_TEST_DATABASE');
if (! is_string($name) || ! preg_match('/^erp_(qa|test)_[a-z0-9_]+$/D', $name)) {
    fwrite(STDERR, "Definir ERP_G3_G4_TEST_DATABASE=erp_qa_...; no se toca ninguna otra base.\n");
    exit(2);
}
$user = getenv('ERP_TEST_DB_USERNAME') ?: 'root';
$password = getenv('ERP_TEST_DB_PASSWORD') ?: '';
$pdo = new PDO('mysql:host=127.0.0.1;port=3315;charset=utf8mb4', $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='.$pdo->quote($name))->fetchColumn()) {
    fwrite(STDERR, "La base ya existe. Elegir otro nombre; este script no destruye bases.\n");
    exit(2);
}
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
putenv('APP_ENV=testing');
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 3315,
    'database.connections.mysql.database' => $name, 'database.connections.mysql.username' => $user,
    'database.connections.mysql.password' => $password, 'database.connections.mysql.url' => null]);
DB::purge('mysql');
$code = Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
exit($code);
