<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Services;

use InvalidArgumentException;

final class PhoneNumber
{
    public static function normalize(string $input): string
    {
        $number = preg_replace('/[\s().-]/', '', trim($input));
        if (!is_string($number)) {
            throw new InvalidArgumentException('Enter a valid mobile number.');
        }

        if (preg_match('/\A09\d{9}\z/', $number)) {
            $number = '+63' . substr($number, 1);
        } elseif (preg_match('/\A9\d{9}\z/', $number)) {
            $number = '+63' . $number;
        } elseif (preg_match('/\A639\d{9}\z/', $number)) {
            $number = '+' . $number;
        }

        if (!preg_match('/\A\+[1-9]\d{7,14}\z/', $number)) {
            throw new InvalidArgumentException('Enter a valid mobile number with its country code, such as +639XXXXXXXXX.');
        }

        return $number;
    }

    public static function mask(string $number): string
    {
        $visible = substr($number, -4);
        return str_repeat('*', max(0, strlen($number) - 4)) . $visible;
    }
}
