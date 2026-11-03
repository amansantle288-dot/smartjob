<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmsGateway\Services;

use SMARTJOB\Modules\AccountVerification\Services\PhoneNumber;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;
use SMARTJOB\Modules\AccountVerification\Services\VerificationConfig;

final class SmsService
{
    public function __construct(private readonly ?SmsProviderInterface $providerOverride = null)
    {
    }

    /** @return array{provider: string, message_id: string|null, http_status: int} */
    public function sendVerification(string $recipient, string $otp): array
    {
        $message = "SMARTJOB: Your verification code is {$otp}. It expires in 5 minutes. Do not share this code.";
        return $this->sendText($recipient, $message);
    }

    /** @return array{provider: string, message_id: string|null, http_status: int} */
    public function sendText(string $recipient, string $message): array
    {
        $providerName = $this->providerName();
        $provider = $this->providerOverride ?? match ($providerName) {
            'textbee' => new TextBeeSmsProvider(),
            'twilio' => new TwilioSmsProvider(),
            default => throw new ProviderException('SMS verification is temporarily unavailable. Please try again later.', 'CONFIGURATION'),
        };
        return ['provider' => $providerName, ...$provider->send(PhoneNumber::normalize($recipient), $message)];
    }

    public function configured(): bool
    {
        return match (VerificationConfig::smsProvider()) {
            'textbee' => (string) getenv('TEXTBEE_API_KEY') !== '',
            'twilio' => (string) getenv('TWILIO_ACCOUNT_SID') !== '' && (string) getenv('TWILIO_AUTH_TOKEN') !== '' && (string) getenv('TWILIO_PHONE_NUMBER') !== '',
            default => false,
        };
    }

    public function providerName(): string
    {
        return $this->providerOverride === null ? VerificationConfig::smsProvider() : 'mock';
    }
}
