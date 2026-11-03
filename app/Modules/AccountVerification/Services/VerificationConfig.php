<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Services;

use RuntimeException;

final class VerificationConfig
{
    public static function expiryMinutes(): int
    {
        $stored = self::systemSetting('verification.otp_expiry_minutes');
        return $stored === null
            ? self::boundedInteger('OTP_EXPIRY_MINUTES', self::fileConfig('verification.php')['otp_expiry_minutes'], 1, 5)
            : self::validateInteger('OTP_EXPIRY_MINUTES', $stored, 1, 5);
    }

    public static function maxAttempts(): int
    {
        $stored = self::systemSetting('verification.otp_max_attempts');
        return $stored === null
            ? self::boundedInteger('OTP_MAX_ATTEMPTS', self::fileConfig('verification.php')['otp_max_attempts'], 1, 5)
            : self::validateInteger('OTP_MAX_ATTEMPTS', $stored, 1, 5);
    }

    public static function resendCooldown(): int
    {
        $stored = self::systemSetting('verification.otp_resend_cooldown_seconds');
        return $stored === null
            ? self::boundedInteger('OTP_RESEND_COOLDOWN_SECONDS', self::fileConfig('verification.php')['otp_resend_cooldown_seconds'], 30, 3600)
            : self::validateInteger('OTP_RESEND_COOLDOWN_SECONDS', $stored, 30, 3600);
    }

    public static function emailRequired(): bool
    {
        $setting = self::systemSetting('verification.email_required');
        return $setting === null ? self::boolean('EMAIL_VERIFICATION_REQUIRED', self::fileConfig('verification.php')['email_verification_required']) : $setting === '1';
    }

    public static function phoneRequired(string $role): bool
    {
        $setting = self::systemSetting('verification.phone_required');
        return $setting === null ? self::boolean('PHONE_VERIFICATION_REQUIRED', self::fileConfig('verification.php')['phone_verification_required']) : $setting === '1';
    }

    public static function emailProvider(): string
    {
        return strtolower(trim(self::systemSetting('verification.mail_provider') ?? (string) self::fileConfig('email.php')['provider']));
    }

    public static function smsProvider(): string
    {
        return strtolower(trim(self::systemSetting('verification.sms_provider') ?? (string) self::fileConfig('sms.php')['provider']));
    }

    private static function systemSetting(string $key): ?string
    {
        if (!function_exists('db')) {
            return null;
        }
        $statement = \db()->prepare('SELECT setting_value FROM tbl_system_settings WHERE setting_key = ?');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    private static function boundedInteger(string $key, int $default, int $minimum, int $maximum): int
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return self::validateInteger($key, $value, $minimum, $maximum);
    }

    private static function validateInteger(string $key, string $value, int $minimum, int $maximum): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < $minimum || $integer > $maximum) {
            throw new RuntimeException($key . ' must be between ' . $minimum . ' and ' . $maximum . '.');
        }
        return $integer;
    }

    private static function boolean(string $key, bool $default): bool
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new RuntimeException($key . ' must be true or false.');
        }
        return $parsed;
    }

    private static function fileConfig(string $file): array
    {
        return require dirname(__DIR__, 4) . '/config/' . $file;
    }
}
