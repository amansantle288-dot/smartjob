<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (getenv('APP_ENV') !== 'local') {
    throw new RuntimeException('CoreDatabaseTest must use the local XAMPP environment.');
}
$databaseConfiguration = \SMARTJOB\Core\Database::diagnosticConfiguration();
if ($databaseConfiguration !== ['host' => '127.0.0.1', 'port' => '3306']) {
    throw new RuntimeException('The local XAMPP database host or port is not configured as expected.');
}

$firstConnection = \SMARTJOB\Core\Database::connection();
$secondConnection = \SMARTJOB\Core\Database::connection();

if ($firstConnection !== $secondConnection) {
    throw new RuntimeException('Database::connection() did not reuse its PDO connection.');
}

$charset = $firstConnection->query('SELECT @@character_set_connection')->fetchColumn();
if (strtolower((string) $charset) !== 'utf8mb4') {
    throw new RuntimeException('The PDO connection is not using utf8mb4.');
}

$timezone = $firstConnection->query('SELECT @@session.time_zone')->fetchColumn();
if ($timezone !== '+08:00') {
    throw new RuntimeException('The PDO session is not using the Asia/Manila UTC offset.');
}

if ($firstConnection->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
    throw new RuntimeException('PDO exception mode is not enabled.');
}

if ($firstConnection->getAttribute(PDO::ATTR_EMULATE_PREPARES) !== false) {
    throw new RuntimeException('PDO emulated prepares are enabled.');
}

echo "Reusable PDO connection settings verified.\n";
