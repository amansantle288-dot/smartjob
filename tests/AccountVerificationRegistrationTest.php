<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\AccountVerification\Controllers\OtpVerificationController;
use SMARTJOB\Modules\AccountVerification\Services\OtpService;
use SMARTJOB\Modules\Authentication\Controllers\AuthenticationController;
use SMARTJOB\Modules\SmartMail\Services\EmailProviderInterface;

$provider = new class implements EmailProviderInterface {
    public string $code = '';

    public function send(string $recipient, string $subject, string $text, string $html): array
    {
        if (!preg_match('/code is: (\d{6})/', $text, $matches)) {
            throw new RuntimeException('Mock email provider did not receive an OTP.');
        }
        $this->code = $matches[1];
        return ['message_id' => 'registration-mock-id', 'http_status' => 201];
    }
};

$pdo = db();
$originalMailProvider = getenv('MAIL_PROVIDER');
$userId = 0;
putenv('MAIL_PROVIDER=brevo');

try {
    $_POST = [
        'full_name' => 'Verification Registration Test',
        'email' => 'verification-registration-' . bin2hex(random_bytes(5)) . '@example.invalid',
        'phone_number' => '09171234567',
        'password' => 'Valid-registration-test-password',
        'role' => 'student',
    ];
    $result = (new AuthenticationController(new OtpService($pdo, $provider)))->handle('register');
    $userId = (int) ($_SESSION['pending_verification_user_id'] ?? 0);
    if ($result['page'] !== 'verify_email' || $userId < 1 || isset($_SESSION['user_id'])) {
        throw new RuntimeException('Registration did not leave the user pending email verification.');
    }

    $statement = $pdo->prepare('SELECT status, phone_number, verification_required, phone_verification_required FROM tbl_users WHERE id = ?');
    $statement->execute([$userId]);
    $user = $statement->fetch();
    if (!$user || $user['status'] !== 'pending_verification' || $user['phone_number'] !== '+639171234567' || !(bool) $user['verification_required'] || (bool) $user['phone_verification_required']) {
        throw new RuntimeException('Registration did not persist the expected normalized contact and verification policy.');
    }

    $_POST['otp'] = $provider->code;
    $verified = (new OtpVerificationController(new OtpService($pdo, $provider)))->verify('email');
    if ($verified['page'] !== 'verification_success' || isset($_SESSION['pending_verification_user_id'])) {
        throw new RuntimeException('Correct email verification did not finish registration.');
    }
    if ($pdo->query('SELECT status FROM tbl_users WHERE id = ' . $userId)->fetchColumn() !== 'active') {
        throw new RuntimeException('Email verification did not activate the new student account.');
    }

    echo "Student registration creates a pending account and activates it only after mocked email OTP verification.\n";
} finally {
    $_POST = [];
    unset($_SESSION['pending_verification_user_id'], $_SESSION['user_id']);
    if ($userId > 0) {
        $pdo->prepare('DELETE FROM tbl_users WHERE id = ?')->execute([$userId]);
    }
    $pdo->exec("DELETE FROM tbl_api_provider_logs WHERE provider_name = 'mock'");
    if ($originalMailProvider === false) {
        putenv('MAIL_PROVIDER');
    } else {
        putenv('MAIL_PROVIDER=' . $originalMailProvider);
    }
}
