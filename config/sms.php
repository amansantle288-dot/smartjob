<?php
declare(strict_types=1);

return [
    'provider' => strtolower(trim((string) (getenv('SMS_PROVIDER') ?: 'textbee'))),
    'textbee_api_key' => (string) (getenv('TEXTBEE_API_KEY') ?: ''),
    'textbee_device_id' => (string) (getenv('TEXTBEE_DEVICE_ID') ?: ''),
    'twilio_account_sid' => (string) (getenv('TWILIO_ACCOUNT_SID') ?: ''),
    'twilio_auth_token' => (string) (getenv('TWILIO_AUTH_TOKEN') ?: ''),
    'twilio_phone_number' => (string) (getenv('TWILIO_PHONE_NUMBER') ?: ''),
];
