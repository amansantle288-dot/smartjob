<?php
declare(strict_types=1);

namespace SMARTJOB\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = self::environmentValue('SMARTJOB_DB_HOST', 'DB_HOST', '127.0.0.1');
        $port = self::environmentValue('SMARTJOB_DB_PORT', 'DB_PORT', '3306');
        $name = self::environmentValue('SMARTJOB_DB_NAME', 'DB_NAME', 'smartjob_db');
        $user = self::environmentValue('SMARTJOB_DB_USER', 'DB_USER', 'root');
        $password = self::environmentValue('SMARTJOB_DB_PASSWORD', 'DB_PASSWORD', '');

        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException('The database port configuration is invalid.');
        }
        if ($host === '' || str_contains($host, ';') || !preg_match('/\A[A-Za-z0-9_-]+\z/', $name)) {
            throw new RuntimeException('The database connection configuration is invalid.');
        }

        self::$connection = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        self::$connection->exec("SET time_zone = '+08:00'");

        return self::$connection;
    }

    private static function environmentValue(string $preferred, string $fallback, string $default): string
    {
        $value = getenv($preferred);
        if ($value === false || $value === '') {
            $value = getenv($fallback);
        }

        return $value === false ? $default : $value;
    }
}
