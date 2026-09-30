<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static array $connections = [];

    public static function connection(?string $name = null): PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $name = $name ?: ($config['default'] ?? 'mysql');

        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        $connection = $config['connections'][$name] ?? null;
        if (!$connection || ($connection['driver'] ?? null) !== 'mysql') {
            throw new \RuntimeException("Database connection [{$name}] is not configured.");
        }

        $charset = $connection['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $connection['host'],
            $connection['port'],
            $connection['database'],
            $charset
        );

        self::$connections[$name] = new PDO($dsn, $connection['username'], $connection['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connections[$name];
    }
}
