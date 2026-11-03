<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Controllers;

use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Services\OtpService;
use SMARTJOB\Modules\AccountVerification\Services\PhoneNumber;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class SmsVerificationController
{
    /** @return array{page: string, notice: string} */
    public function handle(string $action): array
    {
        return match ($action) {
            'start_phone_verification' => $this->start(),
            'resend_phone_otp' => $this->resend(),
            default => throw new RuntimeException('Unknown mobile verification action.'),
        };
    }

    public function show(): never
    {
        $userId = EmailVerificationController::verificationUserId();
        $statement = \db()->prepare('SELECT phone_number, phone_verified_at, email_verified_at, verification_required, phone_verification_required FROM tbl_users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user || ((bool) $user['verification_required'] && $user['email_verified_at'] === null)) {
            \redirect('verify_email');
        }
        if ($user['phone_verified_at'] !== null) {
            \redirect('dashboard');
        }
        if (!$user['phone_number']) {
            throw new RuntimeException('No mobile number is available for this account.');
        }
        \render_page('verify_phone', null, [], [
            'verificationRecipient' => PhoneNumber::mask((string) $user['phone_number']),
            'phoneVerificationRequired' => (bool) $user['phone_verification_required'],
        ]);
        exit;
    }

    /** @return array{page: string, notice: string} */
    public function resend(): array
    {
        $userId = EmailVerificationController::verificationUserId();
        try {
            (new OtpService())->issue($userId, 'sms');
            return ['page' => 'verify_phone', 'notice' => 'A new verification code has been requested. Check your mobile phone.'];
        } catch (ProviderException $exception) {
            return ['page' => 'verify_phone', 'notice' => $exception->getMessage()];
        }
    }

    /** @return array{page: string, notice: string} */
    public function start(): array
    {
        $userId = EmailVerificationController::verificationUserId();
        $statement = \db()->prepare('SELECT email_verified_at, phone_verified_at FROM tbl_users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user || $user['email_verified_at'] === null) {
            throw new RuntimeException('Verify your email address before starting mobile verification.');
        }
        if ($user['phone_verified_at'] !== null) {
            return ['page' => 'verification_success', 'notice' => 'Your mobile number is already verified.'];
        }
        return $this->resend();
    }
}
