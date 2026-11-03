<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmartMail\Services;

use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;
use SMARTJOB\Modules\AccountVerification\Services\VerificationConfig;

final class EmailService
{
    public function __construct(private readonly ?EmailProviderInterface $providerOverride = null)
    {
    }

    /** @return array{provider: string, message_id: string|null, http_status: int} */
    public function sendVerification(string $recipient, string $otp): array
    {
        $text = "Welcome to SMARTJOB!\n\nThank you for creating your account.\n\nYour account verification code is: {$otp}\n\nThis code expires in five minutes.\n\nIf you did not request this verification, you may ignore this message.\n\nSMARTJOB Support Team";
        $escapedOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<p>Welcome to SMARTJOB!</p><p>Thank you for creating your account.</p><p>Your account verification code is:</p><p><strong>' . $escapedOtp . '</strong></p><p>This code expires in five minutes.</p><p>If you did not request this verification, you may ignore this message.</p><p>SMARTJOB Support Team</p>';
        return $this->send($recipient, 'SMARTJOB Account Verification', $text, $html);
    }

    /** @return array{provider: string, message_id: string|null, http_status: int} */
    public function sendMessage(string $recipient, string $subject, string $text): array
    {
        return $this->send($recipient, $subject, $text, nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
    }

    /** @return array{provider: string, message_id: string|null, http_status: int} */
    private function send(string $recipient, string $subject, string $text, string $html): array
    {
        $providerName = $this->providerName();
        $provider = $this->providerOverride ?? match ($providerName) {
            'brevo' => new BrevoEmailProvider(),
            'mailjet' => new MailjetEmailProvider(),
            'sendgrid' => new SendGridEmailProvider(),
            'smtp' => new SmtpEmailProvider(),
            default => throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'CONFIGURATION'),
        };
        return ['provider' => $providerName, ...$provider->send($recipient, $subject, $text, $html)];
    }

    public function configured(): bool
    {
        $provider = VerificationConfig::emailProvider();
        $hasSender = filter_var((string) getenv('MAIL_FROM_ADDRESS'), FILTER_VALIDATE_EMAIL) !== false;
        return match ($provider) {
            'brevo' => $hasSender && (string) getenv('BREVO_API_KEY') !== '',
            'mailjet' => $hasSender && (string) getenv('MAILJET_API_KEY') !== '' && (string) getenv('MAILJET_SECRET_KEY') !== '',
            'sendgrid' => $hasSender && (string) getenv('SENDGRID_API_KEY') !== '',
            'smtp' => $hasSender && (string) getenv('SMTP_HOST') !== '' && (string) getenv('SMTP_USERNAME') !== '' && (string) getenv('SMTP_PASSWORD') !== '' && class_exists(\PHPMailer\PHPMailer\PHPMailer::class),
            default => false,
        };
    }

    public function providerName(): string
    {
        return $this->providerOverride === null ? VerificationConfig::emailProvider() : 'mock';
    }
}
