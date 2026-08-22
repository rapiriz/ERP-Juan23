<?php
namespace Shared\Database;

use PDO;
use PDOException;

/**
 * Configuración PDO centralizada — un solo lugar para toda la conexión a BD.
 * Todos los Repositories de los 6 módulos deberían usar esta clase.
 */
class Conexion
{
    private static ?PDO $instancia = null;

    public static function obtener(): PDO
    {
        if (self::$instancia === null) {
            $config = require __DIR__ . '/../../../config/database.php';
            try {
                self::$instancia = new PDO(
                    "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                    $config['user'],
                    $config['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                die("Error de conexión: " . $e->getMessage());
            }
        }
        return self::$instancia;
    }
}
