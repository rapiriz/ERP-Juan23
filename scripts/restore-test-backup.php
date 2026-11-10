<?php

use Symfony\Component\Process\Process;

/** Ensayo de restauración: solamente una base NUEVA de prueba en 3315. */
require __DIR__.'/../vendor/autoload.php';
$name = getenv('ERP_RESTORE_TEST_DATABASE');
$file = $argv[1] ?? '';
if (! is_string($name) || ! preg_match('/^erp_(qa|test)_[a-z0-9_]+$/D', $name) || ! is_file($file)) {
    throw new RuntimeException('Indicar SQL y ERP_RESTORE_TEST_DATABASE=erp_qa_...');
}
$sql = file_get_contents($file);
if (preg_match('/^\s*(USE\s|(?:CREATE|DROP)\s+DATABASE\s)/mi', $sql)) {
    throw new RuntimeException('El dump contiene instrucciones de base de datos; requiere revisión manual.');
}
$user = getenv('ERP_TEST_DB_USERNAME') ?: 'root';
$password = getenv('ERP_TEST_DB_PASSWORD') ?: '';
$pdo = new PDO('mysql:host=127.0.0.1;port=3315;charset=utf8mb4', $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='.$pdo->quote($name))->fetchColumn()) {
    throw new RuntimeException('El destino ya existe: no se sobrescribe.');
}
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$binary = getenv('ERP_MYSQL') ?: (is_file('C:/xampp/mysql/bin/mysql.exe') ? 'C:/xampp/mysql/bin/mysql.exe' : 'mysql');
$process = new Process([$binary, '-h', '127.0.0.1', '-P', '3315', '-u', $user, $name], null, ['MYSQL_PWD' => $password]);
$process->setInput(fopen($file, 'rb'));
$process->setTimeout(300);
$process->mustRun();
echo "Restauración de prueba terminada en {$name}. Comparar datos antes de promover una restauración.\n";
