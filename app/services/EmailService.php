<?php
declare(strict_types=1);

final class EmailService
{
    public static function send(string $recipient, string $subject, string $body): void
    {
        $provider = strtolower((string) (getenv('MAIL_PROVIDER') ?: 'none'));
        if ($provider === 'none') {
            throw new RuntimeException('SMTP is not fully configured. The email remains a draft.');
        }
        if (!class_exists(\SMARTJOB\Modules\SmartMail\Services\EmailService::class)) {
            require_once __DIR__ . '/../Modules/AccountVerification/Services/ProviderException.php';
            require_once __DIR__ . '/../Modules/AccountVerification/Services/VerificationConfig.php';
            require_once __DIR__ . '/../Modules/SmartMail/Services/EmailProviderInterface.php';
            require_once __DIR__ . '/../Modules/SmartMail/Services/BrevoEmailProvider.php';
            require_once __DIR__ . '/../Modules/SmartMail/Services/SendGridEmailProvider.php';
            require_once __DIR__ . '/../Modules/SmartMail/Services/SmtpEmailProvider.php';
            require_once __DIR__ . '/../Modules/SmartMail/Services/EmailService.php';
        }
        $service = new \SMARTJOB\Modules\SmartMail\Services\EmailService();
        if (!$service->configured()) {
            throw new RuntimeException('SMTP is not fully configured. The email remains a draft.');
        }
        $service->sendMessage($recipient, $subject, $body);
    }
}
