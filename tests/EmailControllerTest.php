<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\SmartMail\Controllers\EmailController;

foreach (['save_email_draft', 'send_email'] as $action) {
    if (!EmailController::supports($action)) {
        throw new RuntimeException("SMARTMAIL action is not registered: {$action}");
    }
}

$pdo = db();
$pdo->beginTransaction();
$originalMailProvider = getenv('MAIL_PROVIDER');
putenv('MAIL_PROVIDER=none');

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Email Test', 'email-' . bin2hex(random_bytes(6)) . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_email_messages (sender_id, recipient_email, subject, body, status) VALUES (?, ?, ?, ?, 'draft')")
        ->execute([$userId, 'recipient@example.invalid', 'Draft safety test', 'This draft must remain unsent.']);
    $emailId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $userId;
    $_POST = ['email_id' => $emailId];
    try {
        (new EmailController())->handle('send_email');
        throw new RuntimeException('EmailController sent a draft without an SMTP provider.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'SMTP is not fully configured. The email remains a draft.') {
            throw $exception;
        }
    }

    $status = $pdo->prepare('SELECT status FROM tbl_email_messages WHERE id = ?');
    $status->execute([$emailId]);
    if ($status->fetchColumn() !== 'draft') {
        throw new RuntimeException('Unconfigured SMTP changed the email draft status.');
    }
    $deliveryLogs = $pdo->prepare('SELECT COUNT(*) FROM tbl_email_delivery_logs WHERE email_id = ?');
    $deliveryLogs->execute([$emailId]);
    if ((int) $deliveryLogs->fetchColumn() !== 0) {
        throw new RuntimeException('Unconfigured SMTP recorded a delivery attempt.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    if ($originalMailProvider === false) {
        putenv('MAIL_PROVIDER');
    } else {
        putenv('MAIL_PROVIDER=' . $originalMailProvider);
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "SMARTMAIL draft safety and database status verified.\n";
