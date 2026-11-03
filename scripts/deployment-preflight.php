<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$failures = [];
$warnings = [];
$requiredExtensions = ['pdo_mysql', 'fileinfo', 'mbstring', 'openssl', 'dom'];
foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $failures[] = 'Missing PHP extension: ' . $extension;
    }
}
$databaseConfiguration = \SMARTJOB\Core\Database::diagnosticConfiguration();
echo 'Application environment: ' . (getenv('APP_ENV') ?: 'local') . PHP_EOL;
echo 'Environment file: ' . smartjob_environment_config_source() . PHP_EOL;
echo 'Database host: ' . ($databaseConfiguration['host'] !== '' ? $databaseConfiguration['host'] : 'not configured') . PHP_EOL;
echo 'Database port: ' . ($databaseConfiguration['port'] !== '' ? $databaseConfiguration['port'] : 'not configured') . PHP_EOL;
if (in_array('--config-only', $argv ?? [], true)) {
    echo 'Configuration-only mode; no database connection attempted.' . PHP_EOL;
    exit(0);
}
if (!extension_loaded('curl') && !filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
    $failures[] = 'Enable cURL or HTTPS streams (allow_url_fopen and OpenSSL) for external providers.';
}
if (!extension_loaded('zip')) {
    $warnings[] = 'ZIP is unavailable; DOCX upload and review are disabled.';
}

$storagePath = dirname(CredentialStorage::path(str_repeat('a', 64)));
$writablePath = $storagePath;
while (!is_dir($writablePath) && dirname($writablePath) !== $writablePath) {
    $writablePath = dirname($writablePath);
}
if (!is_dir($writablePath) || !is_writable($writablePath)) {
    $failures[] = 'Private storage directory and its parent must be writable by PHP.';
}

try {
    $pdo = db();
    $expectedSchema = [
        'tbl_users' => ['id', 'role', 'status'],
        'tbl_companies' => ['id', 'verification_status', 'logo_key'],
        'tbl_company_members' => ['company_id', 'user_id', 'member_role'],
        'tbl_internship_opportunities' => ['id', 'company_id', 'published_at', 'application_deadline'],
        'tbl_applications' => ['id', 'internship_id', 'student_id'],
        'tbl_notifications' => ['id', 'user_id', 'read_at'],
        'tbl_audit_logs' => ['id', 'action', 'created_at', 'actor_role', 'result', 'description'],
        'tbl_admin_email_logs' => ['id', 'company_id', 'delivery_status', 'created_at'],
    ];
    $columnQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?'
    );
    foreach ($expectedSchema as $table => $columns) {
        foreach ($columns as $column) {
            $columnQuery->execute([$table, $column]);
            if ((int) $columnQuery->fetchColumn() !== 1) {
                $failures[] = 'Missing schema element: ' . $table . '.' . $column;
            }
        }
    }
    echo 'PASS: database connection and schema inspection completed.' . PHP_EOL;
} catch (Throwable) {
    $failures[] = 'Database connection or schema inspection failed.';
}

echo 'PHP ' . PHP_VERSION . ' · APP_ENV=' . (getenv('APP_ENV') ?: 'local') . PHP_EOL;
echo 'Private storage: ' . (is_dir($storagePath) && is_writable($storagePath) ? 'writable' : 'needs directory creation / permission check') . PHP_EOL;
foreach ($warnings as $warning) {
    echo 'WARN: ' . $warning . PHP_EOL;
}
foreach ($failures as $failure) {
    echo 'FAIL: ' . $failure . PHP_EOL;
}
if ($failures) {
    exit(1);
}
echo 'PASS: deployment preflight checks completed.' . PHP_EOL;
