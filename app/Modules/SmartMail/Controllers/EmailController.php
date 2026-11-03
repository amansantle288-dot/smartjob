<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmartMail\Controllers;

use PDO;
use RuntimeException;
use Throwable;

final class EmailController
{
    private const ACTIONS = ['save_email_draft', 'send_email'];

    public static function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    /**
     * @return array{page: string, notice: string|null}
     */
    public function handle(string $action): array
    {
        return match ($action) {
            'save_email_draft' => $this->saveDraft(),
            'send_email' => $this->sendDraft(),
            default => throw new RuntimeException('Unknown SMARTMAIL action.'),
        };
    }

    private function saveDraft(): array
    {
        $userId = $this->userId();
        $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        $subject = trim((string) ($_POST['email_subject'] ?? ''));
        $body = trim((string) ($_POST['email_body'] ?? ''));
        if (!$applicationId || $subject === '' || mb_strlen($subject) > 250 || $body === '' || mb_strlen($body) > 15000) {
            throw new RuntimeException('Choose an application and enter a subject and message.');
        }

        $recipient = \application_counterpart_email($userId, $applicationId);
        \db()->prepare("INSERT INTO tbl_email_messages (sender_id, recipient_email, subject, body, status, application_id) VALUES (?, ?, ?, ?, 'draft', ?)")
            ->execute([$userId, $recipient, $subject, $body, $applicationId]);

        return ['page' => 'email', 'notice' => 'Email saved as a draft. It has not been sent.'];
    }

    private function sendDraft(): array
    {
        $userId = $this->userId();
        $emailId = filter_var($_POST['email_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$emailId) {
            throw new RuntimeException('Invalid email draft.');
        }

        $stmt = \db()->prepare("SELECT * FROM tbl_email_messages WHERE id=? AND sender_id=? AND status IN ('draft','failed')");
        $stmt->execute([$emailId, $userId]);
        $email = $stmt->fetch();
        if (!$email) {
            throw new RuntimeException('Draft not found or it is no longer sendable.');
        }

        try {
            \EmailService::send($email['recipient_email'], $email['subject'], $email['body']);
            \db_transaction(function (PDO $pdo) use ($emailId): void {
                $pdo->prepare("UPDATE tbl_email_messages SET status='sent', provider='smtp', submitted_at=NOW(), error_message=NULL WHERE id=?")
                    ->execute([$emailId]);
                $pdo->prepare("INSERT INTO tbl_email_delivery_logs (email_id, status, details) VALUES (?, 'sent', 'SMTP server accepted the message; recipient delivery is not confirmed.')")
                    ->execute([$emailId]);
            });

            return ['page' => 'email', 'notice' => 'SMTP accepted the message. Delivery to the recipient is not confirmed.'];
        } catch (Throwable $exception) {
            if (str_contains($exception->getMessage(), 'The email remains a draft.')) {
                throw $exception;
            }

            \db()->prepare("UPDATE tbl_email_messages SET status='failed', provider='smtp', error_message=? WHERE id=?")
                ->execute([mb_substr($exception->getMessage(), 0, 1000), $emailId]);
            \db()->prepare("INSERT INTO tbl_email_delivery_logs (email_id, status, details) VALUES (?, 'failed', ?)")
                ->execute([$emailId, mb_substr($exception->getMessage(), 0, 1000)]);
            throw new RuntimeException($exception->getMessage());
        }
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
