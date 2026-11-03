<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Controllers;

use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Models\VerificationDeliveryModel;
use SMARTJOB\Modules\AccountVerification\Services\PhoneNumber;
use SMARTJOB\Modules\AccountVerification\Services\ProviderAuditLogger;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;
use SMARTJOB\Modules\AccountVerification\Services\VerificationConfig;
use SMARTJOB\Modules\SmartMail\Services\BrevoEmailProvider;
use SMARTJOB\Modules\SmartMail\Services\EmailService;
use SMARTJOB\Modules\SmartMail\Services\MailjetEmailProvider;
use SMARTJOB\Modules\SmartMail\Services\SendGridEmailProvider;
use SMARTJOB\Modules\SmartMail\Services\SmtpEmailProvider;
use SMARTJOB\Modules\SmsGateway\Services\SmsService;

final class VerificationAdminController
{
    /** @return array{page: string, notice: string} */
    public function handle(string $action): array
    {
        return match ($action) {
            'admin_test_email' => $this->sendTest('email'),
            'admin_test_sms' => $this->sendTest('sms'),
            'save_verification_settings' => $this->saveSettings(),
            default => throw new RuntimeException('Unknown verification administration action.'),
        };
    }

    public function show(): never
    {
        \require_role('admin');
        \render_page('verification_admin', null, [], $this->viewData());
        exit;
    }

    /** @return array<string, mixed> */
    public function viewData(): array
    {
        \require_role('admin');
        $deliveryLogs = \db()->query(
            'SELECT delivery_channel, provider_name, recipient_masked, delivery_status, error_code, error_message, created_at FROM tbl_verification_delivery_logs ORDER BY verification_delivery_id DESC LIMIT 50'
        )->fetchAll();
        $apiLogs = \db()->query(
            'SELECT provider_name, service_type, response_status, http_status_code, error_code, created_at FROM tbl_api_provider_logs ORDER BY api_log_id DESC LIMIT 50'
        )->fetchAll();
        $emailService = new EmailService();
        $smsService = new SmsService();
        return [
            'deliveryLogs' => $deliveryLogs,
            'apiLogs' => $apiLogs,
            'emailProvider' => VerificationConfig::emailProvider(),
            'smsProvider' => VerificationConfig::smsProvider(),
            'emailConfigured' => $emailService->configured(),
            'smsConfigured' => $smsService->configured(),
            'emailRequired' => VerificationConfig::emailRequired(),
            'phoneRequired' => VerificationConfig::phoneRequired('student'),
            'otpExpiryMinutes' => VerificationConfig::expiryMinutes(),
            'otpMaxAttempts' => VerificationConfig::maxAttempts(),
            'resendCooldown' => VerificationConfig::resendCooldown(),
            'testEmailAllowed' => (string) getenv('VERIFICATION_TEST_EMAIL') !== '',
            'testPhoneAllowed' => (string) getenv('VERIFICATION_TEST_PHONE') !== '',
        ];
    }

    /** @return array{page: string, notice: string} */
    private function saveSettings(): array
    {
        \require_role('admin');
        $mailProvider = strtolower(trim((string) ($_POST['mail_provider'] ?? '')));
        $smsProvider = strtolower(trim((string) ($_POST['sms_provider'] ?? '')));
        if (!in_array($mailProvider, ['brevo', 'mailjet', 'sendgrid', 'smtp'], true) || !in_array($smsProvider, ['textbee', 'twilio'], true)) {
            throw new RuntimeException('Select a supported email and SMS provider.');
        }
        $emailRequired = isset($_POST['email_verification_required']) ? '1' : '0';
        $phoneRequired = isset($_POST['phone_verification_required']) ? '1' : '0';
        $expiryMinutes = $this->settingInteger('otp_expiry_minutes', 1, 5);
        $maxAttempts = $this->settingInteger('otp_max_attempts', 1, 5);
        $cooldownSeconds = $this->settingInteger('otp_resend_cooldown_seconds', 30, 3600);
        \db_transaction(function (\PDO $pdo) use ($mailProvider, $smsProvider, $emailRequired, $phoneRequired, $expiryMinutes, $maxAttempts, $cooldownSeconds): void {
            $save = $pdo->prepare('INSERT INTO tbl_system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $save->execute(['verification.mail_provider', $mailProvider]);
            $save->execute(['verification.sms_provider', $smsProvider]);
            $save->execute(['verification.email_required', $emailRequired]);
            $save->execute(['verification.phone_required', $phoneRequired]);
            $save->execute(['verification.otp_expiry_minutes', (string) $expiryMinutes]);
            $save->execute(['verification.otp_max_attempts', (string) $maxAttempts]);
            $save->execute(['verification.otp_resend_cooldown_seconds', (string) $cooldownSeconds]);
            \audit_log((int) \auth_user()['id'], 'verification.settings.updated', 'verification', null);
        });
        return ['page' => 'verification_admin', 'notice' => 'Verification policy and provider selection saved. Provider credentials remain in the server environment.'];
    }

    /** @return array{page: string, notice: string} */
    public function sendTest(string $channel): array
    {
        \require_role('admin');
        $userId = (int) \auth_user()['id'];
        $providerName = $channel === 'email' ? VerificationConfig::emailProvider() : VerificationConfig::smsProvider();
        try {
            if ($channel === 'email') {
                $recipient = strtolower(trim((string) ($_POST['recipient'] ?? '')));
                $authorizedRecipient = strtolower(trim((string) getenv('VERIFICATION_TEST_EMAIL')));
                if ($authorizedRecipient === '' || !hash_equals($authorizedRecipient, $recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Configure an authorized test email address on the server before sending a test.');
                }
                $provider = match ($providerName) {
                    'brevo' => new BrevoEmailProvider(),
                    'mailjet' => new MailjetEmailProvider(),
                    'sendgrid' => new SendGridEmailProvider(),
                    'smtp' => new SmtpEmailProvider(),
                    default => throw new ProviderException('Email provider is not configured.', 'CONFIGURATION'),
                };
                $result = $provider->send($recipient, 'SMARTJOB verification provider test', 'This is a provider configuration test. No verification code was requested.', '<p>This is a provider configuration test. No verification code was requested.</p>');
            } elseif ($channel === 'sms') {
                $configuredRecipient = trim((string) getenv('VERIFICATION_TEST_PHONE'));
                if ($configuredRecipient === '') {
                    throw new RuntimeException('Configure an authorized test mobile number on the server before sending a test.');
                }
                $recipient = PhoneNumber::normalize((string) ($_POST['recipient'] ?? ''));
                $authorizedRecipient = PhoneNumber::normalize($configuredRecipient);
                if (!hash_equals($authorizedRecipient, $recipient)) {
                    throw new RuntimeException('Configure an authorized test mobile number on the server before sending a test.');
                }
                $result = (new SmsService())->sendText($recipient, 'SMARTJOB SMS provider test. No verification code was requested.');
            } else {
                throw new RuntimeException('Unsupported test channel.');
            }
        } catch (ProviderException $exception) {
            \db_transaction(function (\PDO $pdo) use ($userId, $channel, $providerName, $exception): void {
                ProviderAuditLogger::record($pdo, $providerName, $channel, 'failure', $exception->httpStatus, $exception->providerCode);
            });
            throw $exception;
        }

        \db_transaction(function (\PDO $pdo) use ($userId, $channel, $providerName, $recipient, $result): void {
            $masked = $channel === 'email' ? self::maskEmail($recipient) : PhoneNumber::mask($recipient);
            (new VerificationDeliveryModel($pdo))->record(null, $userId, $channel, $providerName, $masked, 'submitted', $result['message_id']);
            ProviderAuditLogger::record($pdo, $providerName, $channel, 'success', $result['http_status'], null);
        });
        return ['page' => 'verification_admin', 'notice' => 'The provider accepted the test request. This does not confirm final delivery.'];
    }

    private static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1)) . '@' . $domain;
    }

    private function settingInteger(string $key, int $minimum, int $maximum): int
    {
        $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > $maximum) {
            throw new RuntimeException('Verification timing and attempt settings are outside the allowed range.');
        }
        return $value;
    }
}
