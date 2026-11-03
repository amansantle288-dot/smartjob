<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmartMail\Services;

use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class SmtpEmailProvider implements EmailProviderInterface
{
    public function send(string $recipient, string $subject, string $text, string $html): array
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'DEPENDENCY_MISSING');
        }
        $required = ['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'MAIL_FROM_ADDRESS'];
        foreach ($required as $key) {
            if (!getenv($key)) {
                throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'CONFIGURATION');
            }
        }
        $port = filter_var(getenv('SMTP_PORT') ?: '587', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (!$port) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'CONFIGURATION');
        }
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) getenv('SMTP_HOST');
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->Username = (string) getenv('SMTP_USERNAME');
            $mail->Password = (string) getenv('SMTP_PASSWORD');
            $encryption = strtolower((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'));
            if ($encryption === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption !== 'none') {
                throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'CONFIGURATION');
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string) getenv('MAIL_FROM_ADDRESS'), (string) (getenv('MAIL_FROM_NAME') ?: 'SMARTJOB'));
            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();
        } catch (ProviderException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new ProviderException('Email verification could not be sent. Please try again later.', 'SMTP_REJECTED');
        }

        return ['message_id' => null, 'http_status' => 200];
    }
}
