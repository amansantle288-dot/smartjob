<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Controllers;

use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Services\OtpService;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class OtpVerificationController
{
    public function __construct(private readonly ?OtpService $otpService = null)
    {
    }

    /** @return array{page: string, notice: string} */
    public function handle(string $action): array
    {
        return match ($action) {
            'verify_email_otp' => $this->verify('email'),
            'verify_phone_otp' => $this->verify('sms'),
            default => throw new RuntimeException('Unknown verification action.'),
        };
    }

    /** @return array{page: string, notice: string} */
    public function verify(string $channel): array
    {
        $userId = EmailVerificationController::verificationUserId();
        $activated = ($this->otpService ?? new OtpService())->verify($userId, $channel, (string) ($_POST['otp'] ?? ''));
        if ($activated) {
            unset($_SESSION['pending_verification_user_id']);
            return ['page' => 'verification_success', 'notice' => 'Your account contact has been verified. You can now sign in.'];
        }

        $statement = \db()->prepare('SELECT email, phone_number, email_verified_at, phone_verification_required, phone_verified_at FROM tbl_users WHERE id = ?');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if ($user && $channel === 'email' && $user['email_verified_at'] !== null) {
            $phone = (string) $user['phone_number'];
            $notice = 'Email verified. Verify your mobile number to finish activating your account.';
            if ((bool) $user['phone_verification_required']) {
                try {
                    (new OtpService())->issue($userId, 'sms');
                } catch (ProviderException $exception) {
                    $notice .= ' ' . $exception->getMessage() . ' Request another code below.';
                }
            }
            return ['page' => 'verify_phone', 'notice' => $notice, 'viewData' => [
                'verificationRecipient' => $phone !== '' ? \SMARTJOB\Modules\AccountVerification\Services\PhoneNumber::mask($phone) : 'your mobile number',
                'phoneVerificationRequired' => (bool) $user['phone_verification_required'],
            ]];
        }
        if ($user && $channel === 'sms' && $user['phone_verified_at'] !== null && !(bool) $user['phone_verification_required']) {
            unset($_SESSION['pending_verification_user_id']);
            return ['page' => 'verification_success', 'notice' => 'Your mobile number has been verified.'];
        }
        if ($channel === 'email') {
            throw new RuntimeException('Email verified. Your account still needs another required verification step.');
        }
        throw new RuntimeException('Mobile number verified. Your account still needs another required verification step.');
    }
}
