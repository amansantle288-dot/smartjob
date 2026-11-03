<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Controllers;

use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Services\OtpService;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class EmailVerificationController
{
    /** @return array{page: string, notice: string} */
    public function handle(string $action): array
    {
        if ($action !== 'resend_email_otp') {
            throw new RuntimeException('Unknown email verification action.');
        }
        return $this->resend();
    }

    public function show(): never
    {
        $userId = self::verificationUserId();
        $statement = \db()->prepare('SELECT email, email_verified_at FROM tbl_users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user) {
            throw new RuntimeException('This verification request is not available.');
        }
        if ($user['email_verified_at'] !== null) {
            \redirect('verify_phone');
        }
        \render_page('verify_email', null, [], ['verificationRecipient' => self::maskEmail((string) $user['email'])]);
        exit;
    }

    /** @return array{page: string, notice: string, viewData?: array<string, mixed>} */
    public function resend(): array
    {
        $userId = self::verificationUserId();
        try {
            (new OtpService())->issue($userId, 'email');
            return ['page' => 'verify_email', 'notice' => 'A new verification code has been requested. Check your email.'];
        } catch (ProviderException $exception) {
            return ['page' => 'verify_email', 'notice' => $exception->getMessage()];
        }
    }

    public static function verificationUserId(): int
    {
        $user = \auth_user();
        $userId = $user ? (int) $user['id'] : (int) ($_SESSION['pending_verification_user_id'] ?? 0);
        if ($userId < 1) {
            \redirect('login');
        }
        return $userId;
    }

    private static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1)) . '@' . $domain;
    }
}
