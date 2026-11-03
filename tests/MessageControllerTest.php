<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\SmartMessage\Controllers\MessageController;

foreach (['create_conversation', 'send_message', 'report_message'] as $action) {
    if (!MessageController::supports($action)) {
        throw new RuntimeException("SMARTMESSAGE action is not registered: {$action}");
    }
}

$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Message Test', 'message-' . bin2hex(random_bytes(6)) . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_conversations (subject, created_by) VALUES (?, ?)')
        ->execute(['Message controller test', $userId]);
    $conversationId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_conversation_participants (conversation_id, user_id) VALUES (?, ?)')
        ->execute([$conversationId, $userId]);
    $pdo->prepare('INSERT INTO tbl_messages (conversation_id, sender_id, body) VALUES (?, ?, ?)')
        ->execute([$conversationId, $userId, 'Test message to report.']);
    $messageId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $userId;
    $_POST = ['message_id' => $messageId, 'reason' => 'Database integration test report.'];
    $result = (new MessageController())->handle('report_message');
    if ($result !== ['page' => 'messages', 'notice' => 'Report submitted for administrator review.']) {
        throw new RuntimeException('Unexpected response from report_message.');
    }

    $report = $pdo->prepare('SELECT reporter_id, reason, status FROM tbl_message_reports WHERE message_id = ?');
    $report->execute([$messageId]);
    $row = $report->fetch();
    if (!$row || (int) $row['reporter_id'] !== $userId || $row['reason'] !== 'Database integration test report.' || $row['status'] !== 'open') {
        throw new RuntimeException('Message report was not persisted correctly.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "SMARTMESSAGE membership checks and report persistence verified.\n";
