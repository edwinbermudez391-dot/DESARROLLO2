<?php
/**
 * Conexión PDO a MySQL
 * Patrón Singleton simple para reutilizar la conexión
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements reales
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$instance->exec("SET time_zone = '-05:00'");
            } catch (PDOException $e) {
                error_log('Conexión MySQL: ' . $e->getMessage());
                throw new RuntimeException('Error de conexión a la base de datos', 0, $e);
            }
        }
        return self::$instance;
    }
}
