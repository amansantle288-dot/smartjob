<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Credentials\Controllers\CredentialController;

foreach (['upload_credential', 'delete_credential'] as $action) {
    if (!CredentialController::supports($action)) {
        throw new RuntimeException("Credential action is not registered: {$action}");
    }
}

foreach (['add_training', 'login'] as $action) {
    if (CredentialController::supports($action)) {
        throw new RuntimeException("Action is assigned to the wrong module: {$action}");
    }
}

$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Credential Test', 'credential-' . bin2hex(random_bytes(6)) . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    $controller = new CredentialController();

    $_POST = ['category' => 'resume'];
    $_FILES = [];
    try {
        $controller->handle('upload_credential');
        throw new RuntimeException('Credential upload accepted a missing file.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Choose a valid file to upload.') {
            throw $exception;
        }
    }

    $_POST = ['credential_id' => '999999999'];
    try {
        $controller->handle('delete_credential');
        throw new RuntimeException('Credential deletion accepted a nonexistent record.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Credential not found.') {
            throw $exception;
        }
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $_FILES = [];
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "Credential controller routing and ownership checks verified.\n";
