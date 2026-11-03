<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Notifications\Controllers\NotificationController;

foreach (['mark_notification_read', 'mark_all_notifications_read'] as $action) {
    if (!NotificationController::supports($action)) {
        throw new RuntimeException("Notification action is not registered: {$action}");
    }
}

$pdo = db();
$pdo->beginTransaction();

try {
    $token = bin2hex(random_bytes(6));
    $createUser = $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')");
    $createUser->execute(['SMARTJOB Notification Test', 'notification-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $createUser->execute(['SMARTJOB Notification Other Test', 'notification-other-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $otherUserId = (int) $pdo->lastInsertId();

    $insert = $pdo->prepare('INSERT INTO tbl_notifications (user_id, title, message) VALUES (?, ?, ?)');
    $insert->execute([$userId, 'Test notification', 'Notification for the test user.']);
    $notificationId = (int) $pdo->lastInsertId();
    $insert->execute([$otherUserId, 'Other notification', 'Notification for the other user.']);
    $otherNotificationId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $userId;
    $controller = new NotificationController();
    $_POST = ['notification_id' => $otherNotificationId];
    try {
        $controller->handle('mark_notification_read');
        throw new RuntimeException('A user was allowed to mark another user’s notification as read.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Notification not found.') {
            throw $exception;
        }
    }

    $_POST = ['notification_id' => $notificationId];
    $controller->handle('mark_notification_read');
    $read = $pdo->prepare('SELECT read_at FROM tbl_notifications WHERE id=? AND user_id=?');
    $read->execute([$notificationId, $userId]);
    if (!$read->fetchColumn()) {
        throw new RuntimeException('The owner’s notification was not marked as read.');
    }

    $insert->execute([$userId, 'Unread test notification', 'This notification should be marked read.']);
    $_POST = [];
    $controller->handle('mark_all_notifications_read');
    $unread = $pdo->prepare('SELECT COUNT(*) FROM tbl_notifications WHERE user_id=? AND read_at IS NULL');
    $unread->execute([$userId]);
    if ((int) $unread->fetchColumn() !== 0) {
        throw new RuntimeException('Mark-all left the user’s notifications unread.');
    }
    $unread->execute([$otherUserId]);
    if ((int) $unread->fetchColumn() !== 1) {
        throw new RuntimeException('Mark-all changed another user’s notifications.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "Notification actions preserve recipient ownership and read state.\n";
