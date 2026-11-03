<?php
declare(strict_types=1);

return [
    'otp_expiry_minutes' => getenv('OTP_EXPIRY_MINUTES') === false || getenv('OTP_EXPIRY_MINUTES') === '' ? 5 : (int) getenv('OTP_EXPIRY_MINUTES'),
    'otp_max_attempts' => getenv('OTP_MAX_ATTEMPTS') === false || getenv('OTP_MAX_ATTEMPTS') === '' ? 5 : (int) getenv('OTP_MAX_ATTEMPTS'),
    'otp_resend_cooldown_seconds' => getenv('OTP_RESEND_COOLDOWN_SECONDS') === false || getenv('OTP_RESEND_COOLDOWN_SECONDS') === '' ? 60 : (int) getenv('OTP_RESEND_COOLDOWN_SECONDS'),
    'email_verification_required' => getenv('EMAIL_VERIFICATION_REQUIRED') === false || getenv('EMAIL_VERIFICATION_REQUIRED') === '' ? true : filter_var(getenv('EMAIL_VERIFICATION_REQUIRED'), FILTER_VALIDATE_BOOLEAN),
    'phone_verification_required' => getenv('PHONE_VERIFICATION_REQUIRED') === false || getenv('PHONE_VERIFICATION_REQUIRED') === '' ? false : filter_var(getenv('PHONE_VERIFICATION_REQUIRED'), FILTER_VALIDATE_BOOLEAN),
];
