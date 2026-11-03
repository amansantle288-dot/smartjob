<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmartMail\Services;

use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class SendGridEmailProvider implements EmailProviderInterface
{
    public function send(string $recipient, string $subject, string $text, string $html): array
    {
        $apiKey = trim((string) getenv('SENDGRID_API_KEY'));
        $senderEmail = trim((string) getenv('MAIL_FROM_ADDRESS'));
        if ($apiKey === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'CONFIGURATION');
        }

        $body = json_encode([
            'personalizations' => [['to' => [['email' => $recipient]]]],
            'from' => ['email' => $senderEmail, 'name' => (string) (getenv('MAIL_FROM_NAME') ?: 'SMARTJOB')],
            'subject' => $subject,
            'content' => [['type' => 'text/plain', 'value' => $text], ['type' => 'text/html', 'value' => $html]],
        ], JSON_THROW_ON_ERROR);
        $handle = curl_init('https://api.sendgrid.com/v3/mail/send');
        if ($handle === false) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'HTTP_CLIENT');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $result = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($handle);
        curl_close($handle);
        if ($result === false || $curlError !== 0) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'NETWORK_ERROR');
        }
        if ($status !== 202) {
            $code = $status === 401 || $status === 403 ? 'AUTHENTICATION_FAILED' : ($status === 429 ? 'RATE_LIMITED' : 'PROVIDER_REJECTED');
            throw new ProviderException('Email verification could not be sent. Please check your email settings or try again later.', $code, $status);
        }
        return ['message_id' => null, 'http_status' => $status];
    }
}
