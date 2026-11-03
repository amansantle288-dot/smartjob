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

        $configuration = self::connectionConfiguration();
        $host = $configuration['host'];
        $port = $configuration['port'];
        $name = $configuration['name'];
        $user = $configuration['user'];
        $password = $configuration['password'];

        if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException('The database port configuration is invalid.');
        }
        if ($host === '' || !preg_match('/\A[A-Za-z0-9_.:-]+\z/', $host)
            || !preg_match('/\A[A-Za-z0-9_-]+\z/', $name)
            || $user === ''
            || (getenv('APP_ENV') === 'production' && $password === '')) {
            throw new RuntimeException('The database connection configuration is invalid.');
        }

        try {
            self::$connection = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 10,
                ]
            );
            self::$connection->exec("SET time_zone = '+08:00'");
        } catch (\PDOException) {
            self::$connection = null;
            throw new RuntimeException('The database connection could not be established. Check the deployment configuration.');
        }

        return self::$connection;
    }

    public static function diagnosticConfiguration(): array
    {
        $configuration = self::connectionConfiguration();
        return [
            'host' => $configuration['host'],
            'port' => $configuration['port'],
        ];
    }

    private static function connectionConfiguration(): array
    {
        $production = getenv('APP_ENV') === 'production';
        return [
            'host' => self::environmentValue('SMARTJOB_DB_HOST', 'DB_HOST', $production ? '' : '127.0.0.1'),
            'port' => self::environmentValue('SMARTJOB_DB_PORT', 'DB_PORT', $production ? '' : '3306'),
            'name' => self::environmentValue('SMARTJOB_DB_NAME', 'DB_NAME', $production ? '' : 'smartjob_db'),
            'user' => self::environmentValue('SMARTJOB_DB_USER', 'DB_USER', $production ? '' : 'root'),
            'password' => self::environmentValue('SMARTJOB_DB_PASSWORD', 'DB_PASSWORD', ''),
        ];
    }

    private static function environmentValue(string $preferred, string $fallback, string $default): string
    {
        $value = getenv($preferred);
        if (($value === false || $value === '') && getenv('APP_ENV') !== 'production') {
            $value = getenv($fallback);
        }

        return $value === false || $value === '' ? $default : $value;
    }
}
