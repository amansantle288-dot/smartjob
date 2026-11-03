<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmartMessage\Controllers;

use PDO;
use RuntimeException;

final class MessageController
{
    private const ACTIONS = ['create_conversation', 'send_message', 'report_message'];

    public static function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    /**
     * @return array{page: string, notice: string}
     */
    public function handle(string $action): array
    {
        return match ($action) {
            'create_conversation' => $this->createConversation(),
            'send_message' => $this->sendMessage(),
            'report_message' => $this->reportMessage(),
            default => throw new RuntimeException('Unknown SMARTMESSAGE action.'),
        };
    }

    public function poll(): never
    {
        \require_login();
        if ((\auth_user()['role'] ?? '') === 'employer') {
            \require_approved_company((int) \auth_user()['id']);
        }
        header('Content-Type: application/json; charset=utf-8');
        $conversationId = filter_var($_GET['conversation_id'] ?? null, FILTER_VALIDATE_INT);
        $afterId = max(0, (int) ($_GET['after'] ?? 0));
        if (!$conversationId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid conversation.']);
            exit;
        }

        $userId = (int) \auth_user()['id'];
        $membership = \db()->prepare('SELECT 1 FROM tbl_conversation_participants WHERE conversation_id = ? AND user_id = ?');
        $membership->execute([$conversationId, $userId]);
        if (!$membership->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation not found.']);
            exit;
        }

        $stmt = \db()->prepare('SELECT m.id, m.body, m.created_at, u.full_name AS sender_name, (m.sender_id = ?) AS mine FROM tbl_messages m JOIN tbl_users u ON u.id = m.sender_id WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id LIMIT 100');
        $stmt->execute([$userId, $conversationId, $afterId]);
        \db()->prepare('UPDATE tbl_conversation_participants SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?')
            ->execute([$conversationId, $userId]);
        echo json_encode(['messages' => $stmt->fetchAll()], JSON_THROW_ON_ERROR);
        exit;
    }

    public function summary(): never
    {
        \require_login();
        $user = \auth_user();
        $userId = (int) $user['id'];
        if (($user['role'] ?? '') === 'employer') {
            \require_approved_company($userId);
        }

        $query = \db()->prepare(
            'SELECT c.id, c.subject,
                (SELECT u.full_name FROM tbl_conversation_participants other
                 JOIN tbl_users u ON u.id=other.user_id
                 WHERE other.conversation_id=c.id AND other.user_id<>? LIMIT 1) AS other_name,
                (SELECT m.body FROM tbl_messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM tbl_messages m
                 WHERE m.conversation_id=c.id AND m.sender_id<>?
                   AND (mine.last_read_at IS NULL OR m.created_at>mine.last_read_at)) AS unread
             FROM tbl_conversations c
             JOIN tbl_conversation_participants mine ON mine.conversation_id=c.id AND mine.user_id=?
             ORDER BY c.updated_at DESC LIMIT 6'
        );
        $query->execute([$userId, $userId, $userId]);
        $threads = $query->fetchAll();
        $unreadQuery = \db()->prepare(
            'SELECT COUNT(*) FROM tbl_messages m
             JOIN tbl_conversation_participants mine ON mine.conversation_id=m.conversation_id AND mine.user_id=?
             WHERE m.sender_id<>? AND (mine.last_read_at IS NULL OR m.created_at>mine.last_read_at)'
        );
        $unreadQuery->execute([$userId, $userId]);
        \SMARTJOB\Modules\Notifications\Controllers\NotificationController::json([
            'unread_count' => (int) $unreadQuery->fetchColumn(),
            'threads' => $threads,
        ]);
    }

    private function createConversation(): array
    {
        $userId = $this->userId();
        $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['message'] ?? ''));
        if (!$applicationId || $subject === '' || mb_strlen($subject) > 180 || $body === '' || mb_strlen($body) > 10000) {
            throw new RuntimeException('Choose an application and enter a subject and message.');
        }

        $conversationId = \open_application_conversation($userId, $applicationId, $subject, $body);
        $_GET['conversation_id'] = (string) $conversationId;

        return ['page' => 'messages', 'notice' => 'SMARTMESSAGE conversation started.'];
    }

    private function sendMessage(): array
    {
        $userId = $this->userId();
        $conversationId = filter_var($_POST['conversation_id'] ?? null, FILTER_VALIDATE_INT);
        $body = trim((string) ($_POST['message'] ?? ''));
        if (!$conversationId || $body === '' || mb_strlen($body) > 10000) {
            throw new RuntimeException('Enter a message of 1–10,000 characters.');
        }
        $conversationId = (int) $conversationId;

        $membership = \db()->prepare('SELECT 1 FROM tbl_conversation_participants WHERE conversation_id=? AND user_id=?');
        $membership->execute([$conversationId, $userId]);
        if (!$membership->fetchColumn()) {
            throw new RuntimeException('You are not a participant in this conversation.');
        }

        $rate = \db()->prepare('SELECT COUNT(*) FROM tbl_messages WHERE sender_id=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
        $rate->execute([$userId]);
        if ((int) $rate->fetchColumn() >= 12) {
            throw new RuntimeException('Message rate limit reached. Wait a minute before sending more.');
        }

        \db_transaction(function (PDO $pdo) use ($conversationId, $userId, $body): void {
            $pdo->prepare('INSERT INTO tbl_messages (conversation_id, sender_id, body) VALUES (?, ?, ?)')->execute([$conversationId, $userId, $body]);
            $pdo->prepare('UPDATE tbl_conversations SET updated_at=NOW() WHERE id=?')->execute([$conversationId]);
            $pdo->prepare('UPDATE tbl_conversation_participants SET last_read_at=NOW() WHERE conversation_id=? AND user_id=?')->execute([$conversationId, $userId]);
            $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT user_id, 'New SMARTMESSAGE', ?, 'conversation', ? FROM tbl_conversation_participants WHERE conversation_id=? AND user_id<>?")
                ->execute([mb_substr($body, 0, 250), $conversationId, $conversationId, $userId]);
            $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'message.sent', 'conversation', ?)")
                ->execute([$userId, $conversationId]);
        });

        $_GET['conversation_id'] = (string) $conversationId;

        return ['page' => 'messages', 'notice' => 'Message sent.'];
    }

    private function reportMessage(): array
    {
        $userId = $this->userId();
        $messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (!$messageId || $reason === '' || mb_strlen($reason) > 1000) {
            throw new RuntimeException('Provide a brief reason for the report.');
        }

        $stmt = \db()->prepare('SELECT m.conversation_id FROM tbl_messages m JOIN tbl_conversation_participants cp ON cp.conversation_id=m.conversation_id WHERE m.id=? AND cp.user_id=?');
        $stmt->execute([$messageId, $userId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Message not found in your conversations.');
        }

        \db()->prepare('INSERT INTO tbl_message_reports (message_id, reporter_id, reason) VALUES (?, ?, ?)')
            ->execute([$messageId, $userId, $reason]);

        return ['page' => 'messages', 'notice' => 'Report submitted for administrator review.'];
    }

    private function userId(): int
    {
        \require_login();
        if ((\auth_user()['role'] ?? '') === 'employer') {
            \require_approved_company((int) \auth_user()['id']);
        }

        return (int) \auth_user()['id'];
    }
}
