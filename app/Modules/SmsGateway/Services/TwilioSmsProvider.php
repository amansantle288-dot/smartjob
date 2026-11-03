<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmsGateway\Services;

use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class TwilioSmsProvider implements SmsProviderInterface
{
    public function send(string $recipient, string $message): array
    {
        $accountSid = trim((string) getenv('TWILIO_ACCOUNT_SID'));
        $authToken = (string) getenv('TWILIO_AUTH_TOKEN');
        $from = trim((string) getenv('TWILIO_PHONE_NUMBER'));
        if ($accountSid === '' || $authToken === '' || $from === '') {
            throw new ProviderException('SMS verification is temporarily unavailable. Please try again later.', 'CONFIGURATION');
        }
        $handle = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json');
        if ($handle === false) {
            throw new ProviderException('SMS verification is temporarily unavailable. Please try again later.', 'HTTP_CLIENT');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['To' => $recipient, 'From' => $from, 'Body' => $message]),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $accountSid . ':' . $authToken,
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
        if ($status !== 201) {
            $code = $status === 401 || $status === 403 ? 'AUTHENTICATION_FAILED' : ($status === 429 ? 'RATE_LIMITED' : 'PROVIDER_REJECTED');
            throw new ProviderException('SMS verification could not be sent. Please try again later.', $code, $status);
        }
        $data = json_decode((string) $result, true);
        return ['message_id' => is_array($data) && isset($data['sid']) ? substr((string) $data['sid'], 0, 190) : null, 'http_status' => $status];
    }
}
