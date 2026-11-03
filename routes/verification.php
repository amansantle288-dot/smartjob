<?php
declare(strict_types=1);

return [
    'get' => [
        'verify_email' => \SMARTJOB\Modules\AccountVerification\Controllers\EmailVerificationController::class,
        'verify_phone' => \SMARTJOB\Modules\AccountVerification\Controllers\SmsVerificationController::class,
        'verification_admin' => \SMARTJOB\Modules\AccountVerification\Controllers\VerificationAdminController::class,
    ],
    'post' => [
        'resend_email_otp' => \SMARTJOB\Modules\AccountVerification\Controllers\EmailVerificationController::class,
        'resend_phone_otp' => \SMARTJOB\Modules\AccountVerification\Controllers\SmsVerificationController::class,
        'start_phone_verification' => \SMARTJOB\Modules\AccountVerification\Controllers\SmsVerificationController::class,
        'verify_email_otp' => \SMARTJOB\Modules\AccountVerification\Controllers\OtpVerificationController::class,
        'verify_phone_otp' => \SMARTJOB\Modules\AccountVerification\Controllers\OtpVerificationController::class,
        'admin_test_email' => \SMARTJOB\Modules\AccountVerification\Controllers\VerificationAdminController::class,
        'admin_test_sms' => \SMARTJOB\Modules\AccountVerification\Controllers\VerificationAdminController::class,
        'save_verification_settings' => \SMARTJOB\Modules\AccountVerification\Controllers\VerificationAdminController::class,
    ],
];
