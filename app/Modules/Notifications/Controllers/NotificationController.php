<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Notifications\Controllers;

use RuntimeException;

final class NotificationController
{
    public static function supports(string $action): bool
    {
        return in_array($action, ['mark_notification_read', 'mark_all_notifications_read'], true);
    }

    public function handle(string $action): array
    {
        \require_login();
        $userId = (int) \auth_user()['id'];
        if ($action === 'mark_all_notifications_read') {
            \db()->prepare('UPDATE tbl_notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')
                ->execute([$userId]);
            return ['page' => 'notifications', 'notice' => 'All notifications marked as read.'];
        }
        if ($action !== 'mark_notification_read') {
            throw new RuntimeException('Unknown notification action.');
        }

        $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$notificationId) {
            throw new RuntimeException('Select a valid notification.');
        }
        $update = \db()->prepare('UPDATE tbl_notifications SET read_at=COALESCE(read_at, NOW()) WHERE id=? AND user_id=?');
        $update->execute([$notificationId, $userId]);
        if ($update->rowCount() === 0) {
            $exists = \db()->prepare('SELECT id FROM tbl_notifications WHERE id=? AND user_id=?');
            $exists->execute([$notificationId, $userId]);
            if (!$exists->fetchColumn()) {
                throw new RuntimeException('Notification not found.');
            }
        }
        return ['page' => 'notifications', 'notice' => 'Notification marked as read.'];
    }

    public function feed(): never
    {
        \require_login();
        $userId = (int) \auth_user()['id'];
        $unread = \db()->prepare('SELECT COUNT(*) FROM tbl_notifications WHERE user_id=? AND read_at IS NULL');
        $unread->execute([$userId]);
        $query = \db()->prepare('SELECT id, title, message, related_type, related_id, read_at, created_at FROM tbl_notifications WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 10');
        $query->execute([$userId]);
        $role = (string) \auth_user()['role'];
        $notifications = array_map(static function (array $notification) use ($role): array {
            $notification['url'] = self::destination($notification['related_type'], $notification['related_id'], $role);
            return $notification;
        }, $query->fetchAll());
        self::json(['unread_count' => (int) $unread->fetchColumn(), 'notifications' => $notifications]);
    }

    private static function destination(?string $type, mixed $id, string $role): string
    {
        $validId = filter_var($id, FILTER_VALIDATE_INT);
        return match ($type) {
            'application' => 'index.php?page=applications' . ($validId ? '&application_id=' . (int) $validId : ''),
            'conversation' => 'index.php?page=messages' . ($validId ? '&conversation_id=' . (int) $validId : ''),
            'company' => $role === 'admin' ? 'index.php?page=admin_companies' : 'index.php?page=company_verification',
            'security' => $role === 'admin' ? 'index.php?page=admin_audit' : 'index.php?page=notifications',
            'training' => 'index.php?page=training',
            'placement' => $role === 'coordinator' ? 'index.php?page=placements' : 'index.php?page=dashboard',
            default => 'index.php?page=notifications',
        };
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
        exit;
    }
}
