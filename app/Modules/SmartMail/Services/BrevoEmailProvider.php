<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmartMail\Services;

use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class BrevoEmailProvider implements EmailProviderInterface
{
    public function send(string $recipient, string $subject, string $text, string $html): array
    {
        $apiKey = trim((string) getenv('BREVO_API_KEY'));
        $senderEmail = trim((string) getenv('MAIL_FROM_ADDRESS'));
        if ($apiKey === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'CONFIGURATION');
        }

        $body = json_encode([
            'sender' => ['email' => $senderEmail, 'name' => (string) (getenv('MAIL_FROM_NAME') ?: 'SMARTJOB')],
            'to' => [['email' => $recipient]],
            'subject' => $subject,
            'textContent' => $text,
            'htmlContent' => $html,
        ], JSON_THROW_ON_ERROR);

        $response = self::request(
            'https://api.brevo.com/v3/smtp/email',
            ['api-key: ' . $apiKey],
            $body
        );
        if ($response['status'] !== 201) {
            throw new ProviderException('Email verification could not be sent. Please check your email settings or try again later.', self::errorCode($response['status']), $response['status']);
        }

        return ['message_id' => self::responseId($response['body'], 'messageId'), 'http_status' => $response['status']];
    }

    /** @return array{status: int, body: string} */
    private static function request(string $url, array $headers, string $body): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'HTTP_CLIENT');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
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
        return ['status' => $status, 'body' => (string) $result];
    }

    private static function responseId(string $body, string $key): ?string
    {
        $data = json_decode($body, true);
        return is_array($data) && isset($data[$key]) ? substr((string) $data[$key], 0, 190) : null;
    }

    private static function errorCode(int $status): string
    {
        return match ($status) {
            401, 403 => 'AUTHENTICATION_FAILED',
            429 => 'RATE_LIMITED',
            400 => 'INVALID_REQUEST',
            default => 'PROVIDER_REJECTED',
        };
    }
}
