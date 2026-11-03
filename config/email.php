<?php
declare(strict_types=1);

return [
    'provider' => strtolower(trim((string) (getenv('MAIL_PROVIDER') ?: 'mailjet'))),
    'from_address' => (string) (getenv('MAIL_FROM_ADDRESS') ?: ''),
    'from_name' => (string) (getenv('MAIL_FROM_NAME') ?: 'SMARTJOB'),
    'brevo_api_key' => (string) (getenv('BREVO_API_KEY') ?: ''),
    'mailjet_api_key' => (string) (getenv('MAILJET_API_KEY') ?: ''),
    'mailjet_secret_key' => (string) (getenv('MAILJET_SECRET_KEY') ?: ''),
    'sendgrid_api_key' => (string) (getenv('SENDGRID_API_KEY') ?: ''),
];
