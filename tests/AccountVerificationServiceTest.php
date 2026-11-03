<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\AccountVerification\Services\OtpService;
use SMARTJOB\Modules\AccountVerification\Services\PhoneNumber;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;
use SMARTJOB\Modules\SmartMail\Services\EmailProviderInterface;
use SMARTJOB\Modules\SmsGateway\Services\SmsProviderInterface;

$pdo = db();
$userIds = [];
$createUser = static function (string $suffix) use ($pdo, &$userIds): int {
    $email = 'verification-' . $suffix . '-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, phone_number, password_hash, role, status, verification_required, phone_verification_required) VALUES ('Verification Test', ?, '+639171234567', 'test-hash', 'student', 'pending_verification', 1, 0)")
        ->execute([$email]);
    $id = (int) $pdo->lastInsertId();
    $userIds[] = $id;
    return $id;
};

$fakeEmail = new class implements EmailProviderInterface {
    public string $code = '';

    public function send(string $recipient, string $subject, string $text, string $html): array
    {
        if (!preg_match('/code is: (\d{6})/', $text, $matches)) {
            throw new RuntimeException('Mock email did not contain the expected OTP format.');
        }
        $this->code = $matches[1];
        return ['message_id' => 'mock-email-id', 'http_status' => 201];
    }
};

$fakeSms = new class implements SmsProviderInterface {
    public string $message = '';
    public string $code = '';

    public function send(string $recipient, string $message): array
    {
        if ($recipient !== '+639171234567') {
            throw new RuntimeException('SMS provider did not receive normalized E.164 phone number.');
        }
        $this->message = $message;
        if (preg_match('/code is (\d{6})/', $message, $matches)) {
            $this->code = $matches[1];
        }
        return ['message_id' => 'mock-sms-id', 'http_status' => 200];
    }
};

try {
    if (PhoneNumber::normalize('0917 123 4567') !== '+639171234567') {
        throw new RuntimeException('Philippine mobile number normalization failed.');
    }
    try {
        PhoneNumber::normalize('not-a-phone-number');
        throw new RuntimeException('Invalid mobile number was accepted.');
    } catch (InvalidArgumentException) {
    }

    $userId = $createUser('correct');
    $otp = new OtpService($pdo, $fakeEmail, $fakeSms);
    $otp->issue($userId, 'email');
    if (!preg_match('/\A\d{6}\z/', $fakeEmail->code)) {
        throw new RuntimeException('Email provider did not receive a six-digit OTP.');
    }
    $storedHash = $pdo->query('SELECT otp_hash FROM tbl_account_verifications WHERE user_id = ' . $userId . ' ORDER BY account_verification_id DESC LIMIT 1')->fetchColumn();
    if (!is_string($storedHash) || hash_equals($storedHash, $fakeEmail->code)) {
        throw new RuntimeException('The OTP was not stored as a keyed hash.');
    }
    if (!$otp->verify($userId, 'email', $fakeEmail->code)) {
        throw new RuntimeException('The correct email OTP did not activate an email-verified account.');
    }
    if ($pdo->query('SELECT status FROM tbl_users WHERE id = ' . $userId)->fetchColumn() !== 'active') {
        throw new RuntimeException('Required email verification did not activate the account.');
    }
    try {
        $otp->verify($userId, 'email', $fakeEmail->code);
        throw new RuntimeException('A verified OTP was reused successfully.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'A verified OTP was reused successfully.') {
            throw $exception;
        }
    }

    $smsUserId = $createUser('sms');
    $otp->issue($smsUserId, 'sms');
    if (!str_contains($fakeSms->message, 'expires in 5 minutes') || !preg_match('/\A\d{6}\z/', $fakeSms->code)) {
        throw new RuntimeException('SMS provider did not receive the expected expiring code message.');
    }
    if ($otp->verify($smsUserId, 'sms', $fakeSms->code)) {
        throw new RuntimeException('Optional SMS verification activated an account whose required email is still unverified.');
    }
    if ($pdo->query('SELECT status FROM tbl_users WHERE id = ' . $smsUserId)->fetchColumn() !== 'pending_verification') {
        throw new RuntimeException('Account activated before required email verification.');
    }

    $requiredSmsUserId = $createUser('required-sms');
    $pdo->prepare('UPDATE tbl_users SET phone_verification_required = 1 WHERE id = ?')->execute([$requiredSmsUserId]);
    $otp->issue($requiredSmsUserId, 'email');
    $emailCode = $fakeEmail->code;
    if ($otp->verify($requiredSmsUserId, 'email', $emailCode)) {
        throw new RuntimeException('Email verification activated an account requiring SMS verification.');
    }
    $otp->issue($requiredSmsUserId, 'sms');
    if (!$otp->verify($requiredSmsUserId, 'sms', $fakeSms->code)) {
        throw new RuntimeException('Account did not activate after all required contacts were verified.');
    }

    $attemptUserId = $createUser('attempts');
    $otp->issue($attemptUserId, 'email');
    $wrongCode = $fakeEmail->code === '111111' ? '222222' : '111111';
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        try {
            $otp->verify($attemptUserId, 'email', $wrongCode);
            throw new RuntimeException('An incorrect OTP was accepted.');
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'An incorrect OTP was accepted.') {
                throw $exception;
            }
        }
    }
    $attemptCount = (int) $pdo->query('SELECT attempt_count FROM tbl_account_verifications WHERE user_id = ' . $attemptUserId . ' ORDER BY account_verification_id DESC LIMIT 1')->fetchColumn();
    if ($attemptCount !== 5) {
        throw new RuntimeException('Incorrect OTP attempts were not capped at five.');
    }

    $expiredUserId = $createUser('expired');
    $otp->issue($expiredUserId, 'email');
    $pdo->prepare('UPDATE tbl_account_verifications SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user_id = ?')->execute([$expiredUserId]);
    try {
        $otp->verify($expiredUserId, 'email', $fakeEmail->code);
        throw new RuntimeException('An expired OTP was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'An expired OTP was accepted.') {
            throw $exception;
        }
    }

    $cooldownUserId = $createUser('cooldown');
    $otp->issue($cooldownUserId, 'email');
    try {
        $otp->issue($cooldownUserId, 'email');
        throw new RuntimeException('An immediate resend bypassed the cooldown.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'An immediate resend bypassed the cooldown.') {
            throw $exception;
        }
    }

    $resendLimitUserId = $createUser('resend-limit');
    for ($request = 0; $request < 5; $request++) {
        $otp->issue($resendLimitUserId, 'email');
        $pdo->prepare('UPDATE tbl_account_verifications SET created_at = DATE_SUB(NOW(), INTERVAL 61 SECOND) WHERE user_id = ?')->execute([$resendLimitUserId]);
    }
    try {
        $otp->issue($resendLimitUserId, 'email');
        throw new RuntimeException('The hourly resend limit was bypassed.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'The hourly resend limit was bypassed.') {
            throw $exception;
        }
    }

    $failedEmail = new class implements EmailProviderInterface {
        public function send(string $recipient, string $subject, string $text, string $html): array
        {
            throw new ProviderException('Email verification could not be sent. Please try again later.', 'AUTHENTICATION_FAILED', 401);
        }
    };
    $failedUserId = $createUser('provider-failure');
    try {
        (new OtpService($pdo, $failedEmail))->issue($failedUserId, 'email');
        throw new RuntimeException('Provider rejection was not surfaced.');
    } catch (ProviderException $exception) {
        if ($exception->providerCode !== 'AUTHENTICATION_FAILED') {
            throw $exception;
        }
    }
    $failedLog = $pdo->prepare('SELECT delivery_status, error_code FROM tbl_verification_delivery_logs WHERE user_id = ? ORDER BY verification_delivery_id DESC LIMIT 1');
    $failedLog->execute([$failedUserId]);
    if ($failedLog->fetch() !== ['delivery_status' => 'failed', 'error_code' => 'AUTHENTICATION_FAILED']) {
        throw new RuntimeException('Provider failure was not sanitized and recorded.');
    }

    echo "OTP hashing, expiry, attempt limits, resend cooldown, single-use, provider failures, and phone normalization verified.\n";
} finally {
    if ($userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $pdo->prepare("DELETE FROM tbl_users WHERE id IN ({$placeholders})")->execute($userIds);
    }
    $pdo->exec("DELETE FROM tbl_api_provider_logs WHERE provider_name = 'mock'");
}
