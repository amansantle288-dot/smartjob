<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$environmentFile = tempnam(sys_get_temp_dir(), 'smartjob-env-');
if ($environmentFile === false) {
    throw new RuntimeException('Could not create an isolated environment test file.');
}

$previous = getenv('SMARTJOB_ENV_TEST_VALUE');
$previousStorage = getenv('SMARTJOB_PRIVATE_STORAGE');
$previousEnvironment = getenv('APP_ENV');
$previousDatabaseHost = getenv('SMARTJOB_DB_HOST');
$previousDatabasePort = getenv('SMARTJOB_DB_PORT');
$previousLegacyDatabaseHost = getenv('DB_HOST');
$previousLegacyDatabasePort = getenv('DB_PORT');
$storageDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smartjob-storage-test-' . bin2hex(random_bytes(6));
$environmentDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smartjob-env-selection-' . bin2hex(random_bytes(6));
$sourceKey = str_repeat('a', 64);
$snapshotKey = null;
try {
    putenv('SMARTJOB_ENV_TEST_VALUE');
    file_put_contents($environmentFile, "SMARTJOB_ENV_TEST_VALUE=\"loaded from test\"\n");
    load_local_environment($environmentFile);
    if (getenv('SMARTJOB_ENV_TEST_VALUE') !== 'loaded from test') {
        throw new RuntimeException('The explicit environment file was not loaded.');
    }

    $projectDirectory = $environmentDirectory . DIRECTORY_SEPARATOR . 'project';
    $publicDirectory = $environmentDirectory . DIRECTORY_SEPARATOR . 'public';
    if (!mkdir($projectDirectory, 0700, true) || !mkdir($publicDirectory, 0700, true)) {
        throw new RuntimeException('Could not create isolated environment-selection directories.');
    }
    foreach (['.env', '.env.local', '.env.production'] as $fileName) {
        if (file_put_contents($projectDirectory . DIRECTORY_SEPARATOR . $fileName, "APP_ENV=test\n") === false) {
            throw new RuntimeException('Could not create an isolated environment-selection file.');
        }
    }
    if (select_environment_file($projectDirectory, 'local', 'localhost', null, $publicDirectory)
            !== $projectDirectory . DIRECTORY_SEPARATOR . '.env.local'
        || select_environment_file($projectDirectory, 'production', 'smartjob.kesug.com', null, $publicDirectory)
            !== $projectDirectory . DIRECTORY_SEPARATOR . '.env.production') {
        throw new RuntimeException('Local and production environment files were not selected independently.');
    }
    $outsideProductionFile = dirname($projectDirectory) . DIRECTORY_SEPARATOR . '.env.production';
    file_put_contents($outsideProductionFile, "APP_ENV=production\n");
    if (select_environment_file($projectDirectory, 'production', 'smartjob.kesug.com', null, $publicDirectory)
        !== $outsideProductionFile) {
        throw new RuntimeException('A private production environment file outside the web root was not preferred.');
    }

    putenv('APP_ENV=production');
    putenv('SMARTJOB_DB_HOST');
    putenv('SMARTJOB_DB_PORT');
    putenv('DB_HOST=127.0.0.1');
    putenv('DB_PORT=3306');
    $productionDatabaseConfiguration = \SMARTJOB\Core\Database::diagnosticConfiguration();
    if ($productionDatabaseConfiguration !== ['host' => '', 'port' => '']) {
        throw new RuntimeException('Production database configuration silently fell back to local defaults.');
    }
    try {
        \SMARTJOB\Core\Database::connection();
        throw new RuntimeException('Production database startup accepted missing connection configuration.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'The database port configuration is invalid.') {
            throw $exception;
        }
    }
    if (array_keys(\SMARTJOB\Core\Database::diagnosticConfiguration()) !== ['host', 'port']) {
        throw new RuntimeException('Database diagnostics expose more than non-secret connection metadata.');
    }
    if ($previousEnvironment === false) {
        putenv('APP_ENV');
    } else {
        putenv('APP_ENV=' . $previousEnvironment);
    }
    foreach ([
        'SMARTJOB_DB_HOST' => $previousDatabaseHost,
        'SMARTJOB_DB_PORT' => $previousDatabasePort,
        'DB_HOST' => $previousLegacyDatabaseHost,
        'DB_PORT' => $previousLegacyDatabasePort,
    ] as $name => $value) {
        putenv($value === false ? $name : $name . '=' . $value);
    }

    $outsideDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smartjob-private-test';
    $insideDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private-test';
    if (smartjob_path_is_within($outsideDirectory, dirname(__DIR__))
        || !smartjob_path_is_within($insideDirectory, dirname(__DIR__))) {
        throw new RuntimeException('Private storage path boundary checks are incorrect.');
    }
    foreach (['C:\\private\\smartjob', 'C:/private/smartjob', '/var/private/smartjob'] as $absolutePath) {
        if (!smartjob_is_absolute_path($absolutePath)) {
            throw new RuntimeException('An absolute Windows or Unix storage path was rejected.');
        }
    }
    if (smartjob_is_absolute_path('private/smartjob') || smartjob_is_absolute_path('C:relative\\smartjob')) {
        throw new RuntimeException('A relative storage path was accepted as absolute.');
    }

    if (!mkdir($storageDirectory, 0700, true)) {
        throw new RuntimeException('Could not create an isolated private-storage test directory.');
    }
    putenv('SMARTJOB_PRIVATE_STORAGE=' . $storageDirectory);
    file_put_contents(CredentialStorage::path($sourceKey), 'private storage snapshot test');
    $snapshot = CredentialStorage::snapshot($sourceKey);
    $snapshotKey = (string) $snapshot['storage_key'];
    if (!is_file(CredentialStorage::path($snapshotKey))
        || hash_file('sha256', CredentialStorage::path($snapshotKey)) !== $snapshot['sha256']) {
        throw new RuntimeException('Private document snapshot creation or permission handling failed.');
    }

    foreach (['.env.production.example', '.htaccess', '.github/workflows/deploy.yml', 'INFINITYFREE_DEPLOYMENT.md', 'scripts/deployment-preflight.php', 'config/verification.php'] as $requiredFile) {
        if (!is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requiredFile))) {
            throw new RuntimeException('Missing deployment artifact: ' . $requiredFile);
        }
    }

    $environmentExample = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env.production.example');
    $workflow = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'deploy.yml');
    $accessRules = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.htaccess');
    if (!is_string($environmentExample)
        || !str_contains($environmentExample, 'APP_ENV=production')
        || !str_contains($environmentExample, 'APP_DEBUG=false')
        || !str_contains($environmentExample, 'SMARTJOB_DB_HOST=sql212.infinityfree.com')
        || !str_contains($environmentExample, 'SMARTJOB_DB_NAME=if0_43340515_smartjob')
        || !str_contains($environmentExample, 'SMARTJOB_DB_USER=if0_43340515')
        || !str_contains($environmentExample, 'SMARTJOB_DB_PASSWORD=SET_PRIVATELY_ON_HOST')
        || !str_contains($environmentExample, 'SMARTJOB_APP_KEY=SET_PRIVATELY_ON_HOST')
        || !str_contains($environmentExample, 'SMARTJOB_PRIVATE_STORAGE=SET_TO_VERIFIED_PRIVATE_PATH')
        || preg_match('/(?:sk-proj-|AQ\.)/', $environmentExample) === 1) {
        throw new RuntimeException('The production environment template is invalid or contains a credential.');
    }
    if (!is_string($workflow)
        || !str_contains($workflow, 'config vendor index.php .htaccess deploy-package/')
        || !str_contains($workflow, 'composer install --no-dev')
        || !str_contains($workflow, 'workflow_dispatch:')
        || !str_contains($workflow, 'push:')
        || !str_contains($workflow, 'default: true')
        || !str_contains($workflow, '**/.env.*')
        || !str_contains($workflow, '**/database/**')
        || !str_contains($workflow, '**/backups/**')
        || !str_contains($workflow, '**/logs/**')
        || !str_contains($workflow, 'dangerous-clean-slate: false')
        || !str_contains($workflow, '**/private/**')
        || !str_contains($workflow, '**/uploads/**')
        || !str_contains($workflow, 'vars.ENABLE_AUTO_DEPLOY == \'true\'')
        || !is_string($accessRules)
        || !str_contains($accessRules, '(app|config|database|tests|vendor')
        || preg_match('/RewriteRule\s+\(\^\|\/\)\\\./i', $accessRules) !== 1
        || preg_match('/<FilesMatch\b[^>]*\\\.env/i', $accessRules) !== 1
        || preg_match('/Require\s+all\s+denied/i', $accessRules) !== 1) {
        throw new RuntimeException('Deployment packaging or production-file protections are incomplete.');
    }
} finally {
    if ($previous === false) {
        putenv('SMARTJOB_ENV_TEST_VALUE');
    } else {
        putenv('SMARTJOB_ENV_TEST_VALUE=' . $previous);
    }
    foreach ([$sourceKey, $snapshotKey] as $key) {
        if ($key !== null) {
            $path = $storageDirectory . DIRECTORY_SEPARATOR . $key;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
    if (is_dir($storageDirectory)) {
        rmdir($storageDirectory);
    }
    foreach (['.env', '.env.local', '.env.production'] as $fileName) {
        $path = $environmentDirectory . DIRECTORY_SEPARATOR . 'project' . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path)) {
            unlink($path);
        }
    }
    $outsideProductionFile = $environmentDirectory . DIRECTORY_SEPARATOR . '.env.production';
    if (is_file($outsideProductionFile)) {
        unlink($outsideProductionFile);
    }
    foreach ([
        $environmentDirectory . DIRECTORY_SEPARATOR . 'project',
        $environmentDirectory . DIRECTORY_SEPARATOR . 'public',
        $environmentDirectory,
    ] as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
    if ($previousEnvironment === false) {
        putenv('APP_ENV');
    } else {
        putenv('APP_ENV=' . $previousEnvironment);
    }
    foreach ([
        'SMARTJOB_DB_HOST' => $previousDatabaseHost,
        'SMARTJOB_DB_PORT' => $previousDatabasePort,
        'DB_HOST' => $previousLegacyDatabaseHost,
        'DB_PORT' => $previousLegacyDatabasePort,
    ] as $name => $value) {
        putenv($value === false ? $name : $name . '=' . $value);
    }
    if ($previousStorage === false) {
        putenv('SMARTJOB_PRIVATE_STORAGE');
    } else {
        putenv('SMARTJOB_PRIVATE_STORAGE=' . $previousStorage);
    }
    unlink($environmentFile);
}

echo "Environment loading, private-path checks, and deployment artifacts verified.\n";
