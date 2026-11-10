<?php

namespace Tests;

use Illuminate\Database\Connection;
use RuntimeException;

final class TestDatabaseSafety
{
    public static function check(Connection $connection): void
    {
        $driver = $connection->getConfig('driver');
        $database = $connection->getDatabaseName();
        if ($driver === 'sqlite' && $database === ':memory:') {
            return;
        }
        $selected = getenv('ERP_G3_G4_TEST_DATABASE');
        if ($driver === 'mysql' && is_string($selected) && $database === $selected
            && preg_match('/^erp_(qa|test)_[a-z0-9_]+$/D', $database)
            && (int) $connection->getConfig('port') === 3315
            && in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost'], true)) {
            return;
        }
        throw new RuntimeException('Conexión de pruebas rechazada: usar SQLite :memory: o una base erp_qa_/erp_test_ explícita en 3315.');
    }
}
