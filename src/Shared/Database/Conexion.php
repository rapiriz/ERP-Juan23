<?php
namespace Shared\Database;

use PDO;
use PDOException;

/**
 * Conexión PDO Singleton centralizada con soporte para Transacciones.
 */
class Conexion
{
    private static ?PDO $instancia = null;

    public static function obtener(): PDO
    {
        if (self::$instancia === null) {
            $config = require __DIR__ . '/../../../config/database.php';
            try {
                $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
                self::$instancia = new PDO(
                    $dsn,
                    $config['user'],
                    $config['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                // Si falla la conexión a MySQL, se devuelve un error descriptivo
                throw new \Exception("Error al conectar a la base de datos MySQL: " . $e->getMessage());
            }
        }
        return self::$instancia;
    }

    /**
     * Iniciar una transacción atómica (todo o nada)
     */
    public static function iniciarTransaccion(): void
    {
        self::obtener()->beginTransaction();
    }

    /**
     * Confirmar la transacción
     */
    public static function confirmar(): void
    {
        if (self::obtener()->inTransaction()) {
            self::obtener()->commit();
        }
    }

    /**
     * Revertir cambios si ocurrió un error
     */
    public static function revertir(): void
    {
        if (self::obtener()->inTransaction()) {
            self::obtener()->rollBack();
        }
    }

    /**
     * Permite inyectar una instancia de PDO (útil para pruebas unitarias)
     */
    public static function setInstancia(?PDO $pdo): void
    {
        self::$instancia = $pdo;
    }
}
