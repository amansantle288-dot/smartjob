<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmartMail\Services;

use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class MailjetEmailProvider implements EmailProviderInterface
{
    public function send(string $recipient, string $subject, string $text, string $html): array
    {
        $apiKey = trim((string) getenv('MAILJET_API_KEY'));
        $secretKey = trim((string) getenv('MAILJET_SECRET_KEY'));
        $senderEmail = trim((string) getenv('MAIL_FROM_ADDRESS'));
        if ($apiKey === '' || $secretKey === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'CONFIGURATION');
        }

        $body = json_encode([
            'Messages' => [[
                'From' => ['Email' => $senderEmail, 'Name' => (string) (getenv('MAIL_FROM_NAME') ?: 'SMARTJOB')],
                'To' => [['Email' => $recipient]],
                'Subject' => $subject,
                'TextPart' => $text,
                'HTMLPart' => $html,
            ]],
        ], JSON_THROW_ON_ERROR);

        // Some Windows/XAMPP Apache installations cannot load php_curl even when
        // the CLI can. Use the HTTPS stream transport as a secure fallback.
        if (function_exists('curl_init')) {
            [$result, $status] = self::sendWithCurl($body, $apiKey, $secretKey);
        } else {
            [$result, $status] = self::sendWithStream($body, $apiKey, $secretKey);
        }
        if ($status !== 200) {
            $code = match ($status) {
                401, 403 => 'AUTHENTICATION_FAILED',
                429 => 'RATE_LIMITED',
                400 => 'INVALID_REQUEST',
                default => 'PROVIDER_REJECTED',
            };
            throw new ProviderException('Email verification could not be sent. Please check your email settings or try again later.', $code, $status);
        }

        $response = json_decode((string) $result, true);
        $messageId = is_array($response)
            ? ($response['Messages'][0]['To'][0]['MessageID'] ?? null)
            : null;
        return ['message_id' => $messageId === null ? null : substr((string) $messageId, 0, 190), 'http_status' => $status];
    }

    /** @return array{0: string, 1: int} */
    private static function sendWithCurl(string $body, string $apiKey, string $secretKey): array
    {
        $handle = curl_init('https://api.mailjet.com/v3.1/send');
        if ($handle === false) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'HTTP_CLIENT');
        }
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . base64_encode($apiKey . ':' . $secretKey),
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $result = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $curlError = curl_errno($handle);
        } finally {
            curl_close($handle);
        }
        if ($result === false || $curlError !== 0) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'NETWORK_ERROR');
        }
        return [(string) $result, $status];
    }

    /** @return array{0: string, 1: int} */
    private static function sendWithStream(string $body, string $apiKey, string $secretKey): array
    {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'HTTP_CLIENT');
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Basic ' . base64_encode($apiKey . ':' . $secretKey),
                    'Content-Type: application/json',
                    'Connection: close',
                ],
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $result = @file_get_contents('https://api.mailjet.com/v3.1/send', false, $context);
        $headers = $http_response_header ?? [];
        $status = 0;
        if (isset($headers[0]) && preg_match('~^HTTP/\S+\s+(\d{3})~', $headers[0], $matches)) {
            $status = (int) $matches[1];
        }
        if ($result === false || $status === 0) {
            throw new ProviderException('Email verification is temporarily unavailable. Please try again later.', 'NETWORK_ERROR');
        }
        return [$result, $status];
    }

}
