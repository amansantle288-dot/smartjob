<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Authentication\Controllers;

use PDO;
use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Services\OtpService;
use SMARTJOB\Modules\AccountVerification\Services\PhoneNumber;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;
use SMARTJOB\Modules\AccountVerification\Services\VerificationConfig;

final class AuthenticationController
{
    private const ACTIONS = ['setup_admin', 'register', 'login', 'logout'];
    private readonly OtpService $otpService;

    public function __construct(?OtpService $otpService = null)
    {
        $this->otpService = $otpService ?? new OtpService();
    }

    public static function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    /**
     * @return array{page: string, notice: string}
     */
    public function handle(string $action): array
    {
        return match ($action) {
            'setup_admin' => $this->setupAdmin(),
            'register' => $this->register(),
            'login' => $this->login(),
            'logout' => $this->logout(),
            default => throw new RuntimeException('Unknown authentication action.'),
        };
    }

    /**
     * @return array{page: string, notice: string}
     */
    private function setupAdmin(): array
    {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        \validate_account($name, $email, $password);

        \db_transaction(function (PDO $pdo) use ($name, $email, $password): void {
            $setting = $pdo->query("SELECT setting_value FROM tbl_system_settings WHERE setting_key = 'initial_admin_created' FOR UPDATE")->fetchColumn();
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM tbl_users WHERE role = 'admin'")->fetchColumn();
            if ($setting !== '0' || $adminCount !== 0) {
                throw new RuntimeException('Administrator setup is already complete.');
            }

            $stmt = $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, 'admin', 'active', NOW())");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->exec("UPDATE tbl_system_settings SET setting_value = '1' WHERE setting_key = 'initial_admin_created'");
            \audit_log($userId, 'admin.setup', 'user', $userId, 'success', 'Initial administrator account created.');
        });

        return ['page' => 'login', 'notice' => 'Administrator account created. You can now sign in.'];
    }

    private function register(): array
    {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'student');
        \validate_account($name, $email, $password);
        if (!in_array($role, ['student', 'employer'], true)) {
            throw new RuntimeException('Select a valid account type.');
        }
        $phone = PhoneNumber::normalize((string) ($_POST['phone_number'] ?? ''));

        $emailRequired = VerificationConfig::emailRequired();
        $phoneRequired = VerificationConfig::phoneRequired($role);
        $status = $emailRequired || $phoneRequired ? 'pending_verification' : 'active';
        $userId = \db_transaction(function (PDO $pdo) use ($name, $email, $password, $phone, $role, $emailRequired, $phoneRequired, $status): int {
            $stmt = $pdo->prepare('INSERT INTO tbl_users (full_name, email, phone_number, password_hash, role, status, verification_required, phone_verification_required) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $role, $status, $emailRequired ? 1 : 0, $phoneRequired ? 1 : 0]);
            $userId = (int) $pdo->lastInsertId();
            if ($role === 'student') {
                $pdo->prepare('INSERT INTO tbl_student_profiles (user_id) VALUES (?)')->execute([$userId]);
            } else {
                $companyName = trim((string) ($_POST['company_name'] ?? ''));
                if ($companyName === '' || mb_strlen($companyName) > 180) {
                    throw new RuntimeException('Enter a company name (maximum 180 characters).');
                }
                $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'pending')")->execute([$companyName]);
                $companyId = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")->execute([$companyId, $userId]);
                $pdo->prepare("INSERT INTO tbl_company_verifications (company_id, status) VALUES (?, 'pending')")->execute([$companyId]);
                $pdo->prepare(
                    "INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id)
                     SELECT id, 'New company registration', ?, 'company', ?
                     FROM tbl_users WHERE role='admin' AND status='active'"
                )->execute(['' . $companyName . ' registered and is awaiting company verification.', $companyId]);
            }
            \audit_log($userId, 'account.registered', 'user', $userId, 'success', 'A new ' . $role . ' account was registered.');

            return $userId;
        });

        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
        if ($status === 'active') {
            \auth_login_by_id($userId);
            return ['page' => 'dashboard', 'notice' => 'Account created. Welcome to SMARTJOB.'];
        }

        $_SESSION['pending_verification_user_id'] = $userId;
        $channel = $emailRequired ? 'email' : 'sms';
        try {
            $this->otpService->issue($userId, $channel);
            $notice = 'Account created. Enter the verification code sent to your ' . ($channel === 'email' ? 'email address.' : 'mobile phone.');
        } catch (ProviderException $exception) {
            $notice = 'Your account was created but the verification message could not be sent. ' . $exception->getMessage() . ' Use the resend option to try again.';
        }
        return ['page' => $channel === 'email' ? 'verify_email' : 'verify_phone', 'notice' => $notice];
    }

    private function login(): never
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $ipHash = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), \app_secret());
        $recent = \db()->prepare('SELECT COUNT(*) FROM tbl_login_attempts WHERE email = ? AND ip_hash = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND succeeded = 0');
        $recent->execute([$email, $ipHash]);
        $recentFailures = (int) $recent->fetchColumn();
        if ($recentFailures >= 8) {
            if ($recentFailures === 8) {
                \db()->prepare('INSERT INTO tbl_login_attempts (email, ip_hash, succeeded) VALUES (?, ?, 0)')->execute([$email, $ipHash]);
                \audit_log(null, 'auth.login.rate_limited', 'authentication', null, 'failure', 'Repeated login attempts were rate limited. Account identifiers and credentials are omitted.');
                \db()->exec(
                    "INSERT INTO tbl_notifications (user_id, title, message, related_type)
                     SELECT id, 'Security alert: repeated login attempts', 'Repeated unsuccessful sign-in attempts were rate limited. Review recent security events.', 'security'
                     FROM tbl_users WHERE role='admin' AND status='active'"
                );
            }
            throw new RuntimeException('Too many unsuccessful attempts. Please wait 15 minutes before trying again.');
        }

        $stmt = \db()->prepare('SELECT id, password_hash, status, verification_required, phone_verification_required, email_verified_at, phone_verified_at FROM tbl_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && $user['status'] === 'pending_verification' && password_verify($password, $user['password_hash'])) {
            \db()->prepare('INSERT INTO tbl_login_attempts (email, ip_hash, succeeded) VALUES (?, ?, 1)')->execute([$email, $ipHash]);
            session_regenerate_id(true);
            unset($_SESSION['_csrf']);
            $_SESSION['pending_verification_user_id'] = (int) $user['id'];
            if ((bool) $user['verification_required'] && $user['email_verified_at'] === null) {
                \redirect('verify_email');
            }
            if ((bool) $user['phone_verification_required'] && $user['phone_verified_at'] === null) {
                \redirect('verify_phone');
            }
        }
        $success = $user && $user['status'] === 'active' && password_verify($password, $user['password_hash']);
        \db()->prepare('INSERT INTO tbl_login_attempts (email, ip_hash, succeeded) VALUES (?, ?, ?)')->execute([$email, $ipHash, $success ? 1 : 0]);
        if (!$success) {
            \audit_log(null, 'auth.login.failed', 'authentication', null, 'failure', 'A login attempt failed. Account identifiers and credentials are omitted.');
            throw new RuntimeException('Email or password is incorrect, or the account is inactive.');
        }

        \auth_login_by_id((int) $user['id']);
        \db()->prepare('UPDATE tbl_users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
        \audit_log((int) $user['id'], 'auth.login', 'user', (int) $user['id']);
        \redirect('dashboard');
    }

    private function logout(): never
    {
        $user = \auth_user();
        if ($user) {
            \audit_log((int) $user['id'], 'auth.logout', 'user', (int) $user['id']);
        }
        $_SESSION = [];
        session_destroy();
        \redirect('home');
    }
}
