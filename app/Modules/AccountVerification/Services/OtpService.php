<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Services;

use PDO;
use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Models\AccountVerificationModel;
use SMARTJOB\Modules\AccountVerification\Models\VerificationDeliveryModel;
use SMARTJOB\Modules\SmartMail\Services\EmailProviderInterface;
use SMARTJOB\Modules\SmartMail\Services\EmailService;
use SMARTJOB\Modules\SmsGateway\Services\SmsProviderInterface;
use SMARTJOB\Modules\SmsGateway\Services\SmsService;

final class OtpService
{
    private readonly EmailService $emailService;
    private readonly SmsService $smsService;

    public function __construct(
        private readonly ?PDO $pdo = null,
        ?EmailProviderInterface $emailProvider = null,
        ?SmsProviderInterface $smsProvider = null
    ) {
        $this->emailService = new EmailService($emailProvider);
        $this->smsService = new SmsService($smsProvider);
    }

    public function issue(int $userId, string $channel, string $purpose = 'account'): void
    {
        if (!in_array($channel, ['email', 'sms'], true) || $purpose !== 'account') {
            throw new RuntimeException('This verification request is not available.');
        }
        $pdo = $this->connection();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = hash_hmac('sha256', $userId . '|' . $channel . '|' . $purpose . '|' . $code, \app_secret());
        [$verificationId, $recipient] = \db_transaction(function (PDO $transaction) use ($userId, $channel, $purpose, $hash): array {
            $userStatement = $transaction->prepare('SELECT email, phone_number, email_verified_at, phone_verified_at FROM tbl_users WHERE id = ? FOR UPDATE');
            $userStatement->execute([$userId]);
            $user = $userStatement->fetch();
            if (!$user) {
                throw new RuntimeException('This verification request is not available.');
            }
            $verifiedAt = $channel === 'email' ? $user['email_verified_at'] : $user['phone_verified_at'];
            if ($verifiedAt !== null) {
                throw new RuntimeException('This contact method is already verified.');
            }
            $recipient = $channel === 'email' ? (string) $user['email'] : (string) $user['phone_number'];
            if ($recipient === '') {
                throw new RuntimeException('Add a valid mobile number before requesting SMS verification.');
            }
            $this->enforceRateLimit($transaction, $userId, $channel, $purpose);
            $model = new AccountVerificationModel($transaction);
            $model->invalidateActive($userId, $channel, $purpose);
            $verificationId = $model->create($userId, $channel, $purpose, $hash, VerificationConfig::expiryMinutes(), VerificationConfig::maxAttempts());
            return [$verificationId, $recipient];
        });

        $providerName = $channel === 'email' ? $this->emailService->providerName() : $this->smsService->providerName();
        $maskedRecipient = $channel === 'email' ? self::maskEmail($recipient) : PhoneNumber::mask($recipient);
        try {
            $result = $channel === 'email'
                ? $this->emailService->sendVerification($recipient, $code)
                : $this->smsService->sendVerification($recipient, $code);
        } catch (ProviderException $exception) {
            $this->recordFailure($verificationId, $userId, $channel, $providerName, $maskedRecipient, $exception);
            throw $exception;
        }
        \db_transaction(function (PDO $transaction) use ($verificationId, $userId, $channel, $result, $maskedRecipient): void {
            (new VerificationDeliveryModel($transaction))->record(
                $verificationId,
                $userId,
                $channel,
                $result['provider'],
                $maskedRecipient,
                'submitted',
                $result['message_id']
            );
            ProviderAuditLogger::record($transaction, $result['provider'], $channel, 'success', $result['http_status'], null);
        });
    }

    public function verify(int $userId, string $channel, string $code): bool
    {
        if (!in_array($channel, ['email', 'sms'], true) || !preg_match('/\A\d{6}\z/', $code)) {
            throw new RuntimeException('Enter the six-digit verification code.');
        }
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                "SELECT account_verification_id, otp_hash, (expires_at <= NOW()) AS is_expired, attempt_count, max_attempts FROM tbl_account_verifications WHERE user_id = ? AND verification_channel = ? AND verification_purpose = 'account' AND verified_at IS NULL AND invalidated_at IS NULL ORDER BY account_verification_id DESC LIMIT 1 FOR UPDATE"
            );
            $statement->execute([$userId, $channel]);
            $verification = $statement->fetch();
            if (!$verification) {
                $pdo->commit();
                throw new RuntimeException('This code is invalid or expired. Request a new code and try again.');
            }

            $verificationId = (int) $verification['account_verification_id'];
            if ((bool) $verification['is_expired']) {
                $pdo->prepare('UPDATE tbl_account_verifications SET invalidated_at = NOW() WHERE account_verification_id = ?')->execute([$verificationId]);
                $pdo->commit();
                throw new RuntimeException('This code has expired. Request a new code and try again.');
            }
            if ((int) $verification['attempt_count'] >= (int) $verification['max_attempts']) {
                $pdo->prepare('UPDATE tbl_account_verifications SET invalidated_at = NOW() WHERE account_verification_id = ?')->execute([$verificationId]);
                $pdo->commit();
                throw new RuntimeException('Too many incorrect codes. Request a new code and try again.');
            }

            $expected = hash_hmac('sha256', $userId . '|' . $channel . '|account|' . $code, \app_secret());
            if (!hash_equals((string) $verification['otp_hash'], $expected)) {
                $newAttempts = (int) $verification['attempt_count'] + 1;
                $invalidate = $newAttempts >= (int) $verification['max_attempts'] ? ', invalidated_at = NOW()' : '';
                $pdo->prepare('UPDATE tbl_account_verifications SET attempt_count = ?' . $invalidate . ' WHERE account_verification_id = ?')
                    ->execute([$newAttempts, $verificationId]);
                $pdo->commit();
                if ($newAttempts >= (int) $verification['max_attempts']) {
                    throw new RuntimeException('Too many incorrect codes. Request a new code and try again.');
                }
                throw new RuntimeException('The verification code is incorrect.');
            }

            $pdo->prepare('UPDATE tbl_account_verifications SET verified_at = NOW() WHERE account_verification_id = ?')->execute([$verificationId]);
            $verifiedColumn = $channel === 'email' ? 'email_verified_at' : 'phone_verified_at';
            $pdo->prepare("UPDATE tbl_users SET {$verifiedColumn} = NOW(), last_verification_at = NOW() WHERE id = ?")->execute([$userId]);
            $activated = AccountActivationService::refresh($pdo, $userId);
            $pdo->prepare('INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)')
                ->execute([$userId, 'account.' . $channel . '.verified', 'user', $userId]);
            $pdo->commit();
            return $activated;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function emailConfigured(): bool
    {
        return $this->emailService->configured();
    }

    public function smsConfigured(): bool
    {
        return $this->smsService->configured();
    }

    private function enforceRateLimit(PDO $pdo, int $userId, string $channel, string $purpose): void
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) AS request_count, SUM(created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)) AS within_cooldown FROM tbl_account_verifications WHERE user_id = ? AND verification_channel = ? AND verification_purpose = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $statement->execute([VerificationConfig::resendCooldown(), $userId, $channel, $purpose]);
        $limits = $statement->fetch();
        if ((int) $limits['request_count'] >= 5) {
            throw new RuntimeException('Too many code requests. Please try again later.');
        }
        if ((int) ($limits['within_cooldown'] ?? 0) > 0) {
            throw new RuntimeException('Please wait before requesting another verification code.');
        }
    }

    private function recordFailure(int $verificationId, int $userId, string $channel, string $provider, string $recipient, ProviderException $exception): void
    {
        \db_transaction(function (PDO $pdo) use ($verificationId, $userId, $channel, $provider, $recipient, $exception): void {
            $pdo->prepare('UPDATE tbl_account_verifications SET invalidated_at = NOW() WHERE account_verification_id = ?')->execute([$verificationId]);
            (new VerificationDeliveryModel($pdo))->record(
                $verificationId,
                $userId,
                $channel,
                $provider,
                $recipient,
                'failed',
                null,
                $exception->providerCode,
                $exception->getMessage()
            );
            ProviderAuditLogger::record($pdo, $provider, $channel, 'failure', $exception->httpStatus, $exception->providerCode);
        });
    }

    private function connection(): PDO
    {
        return $this->pdo ?? \db();
    }

    private static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1)) . '@' . $domain;
    }
}
