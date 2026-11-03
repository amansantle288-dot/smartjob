<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmsGateway\Services;

use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class TextBeeSmsProvider implements SmsProviderInterface
{
    public function send(string $recipient, string $message): array
    {
        $apiKey = trim((string) getenv('TEXTBEE_API_KEY'));
        if ($apiKey === '') {
            throw new ProviderException('SMS verification is temporarily unavailable. Please try again later.', 'CONFIGURATION');
        }

        $payload = ['recipients' => [$recipient], 'message' => $message];
        $deviceId = trim((string) getenv('TEXTBEE_DEVICE_ID'));
        if ($deviceId !== '') {
            $payload['deviceId'] = $deviceId;
        }
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $handle = curl_init('https://api.textbee.dev/api/v1/gateway/send-sms');
        if ($handle === false) {
            throw new ProviderException('SMS verification is temporarily unavailable. Please try again later.', 'HTTP_CLIENT');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['x-api-key: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json'],
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
            throw new ProviderException('SMS verification is temporarily unavailable. Please try again later.', 'NETWORK_ERROR');
        }
        $data = json_decode((string) $result, true);
        if ($status !== 200 || !is_array($data) || ($data['success'] ?? false) !== true) {
            $code = $status === 401 ? 'AUTHENTICATION_FAILED' : ($status === 429 ? 'RATE_LIMITED' : ($status === 400 ? 'GATEWAY_UNAVAILABLE' : 'PROVIDER_REJECTED'));
            throw new ProviderException('SMS verification could not be sent. Please try again later.', $code, $status);
        }
        return ['message_id' => isset($data['smsBatchId']) ? substr((string) $data['smsBatchId'], 0, 190) : null, 'http_status' => $status];
    }
}
