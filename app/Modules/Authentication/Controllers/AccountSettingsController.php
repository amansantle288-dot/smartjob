<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Authentication\Controllers;

use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Services\OtpService;
use SMARTJOB\Modules\AccountVerification\Services\PhoneNumber;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;

final class AccountSettingsController
{
    public function __construct(private readonly OtpService $otpService = new OtpService())
    {
    }

    public function handle(string $action): array
    {
        return match ($action) {
            'update_account_contacts' => $this->updateContacts(),
            'update_account_password' => $this->updatePassword(),
            default => throw new RuntimeException('Unknown account settings action.'),
        };
    }

    private function updateContacts(): array
    {
        \require_role('student');
        $userId = (int) \auth_user()['id'];
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phoneInput = trim((string) ($_POST['phone_number'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new RuntimeException('Enter a valid email address.');
        }
        try {
            $phone = PhoneNumber::normalize($phoneInput);
        } catch (\InvalidArgumentException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
        if (!preg_match('/\A\+639\d{9}\z/', $phone)) {
            throw new RuntimeException('Enter a valid Philippine mobile number, such as +639XXXXXXXXX.');
        }

        $query = \db()->prepare('SELECT email, phone_number, password_hash, verification_required, phone_verification_required FROM tbl_users WHERE id=? AND role=?');
        $query->execute([$userId, 'student']);
        $account = $query->fetch();
        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            throw new RuntimeException('Your current password is incorrect.');
        }
        $emailChanged = $email !== strtolower((string) $account['email']);
        $phoneChanged = $phone !== (string) ($account['phone_number'] ?? '');
        if (!$emailChanged && !$phoneChanged) {
            return ['page' => 'account_settings', 'notice' => 'Your contact information is already up to date.'];
        }

        $mustVerifyEmail = $emailChanged && (bool) $account['verification_required'];
        $mustVerifyPhone = $phoneChanged && (bool) $account['phone_verification_required'];
        \db_transaction(function (\PDO $pdo) use ($email, $phone, $emailChanged, $phoneChanged, $mustVerifyEmail, $mustVerifyPhone, $userId): void {
            $pdo->prepare('UPDATE tbl_users SET email=?, phone_number=?, email_verified_at=IF(?, NULL, email_verified_at), phone_verified_at=IF(?, NULL, phone_verified_at), status=IF(?, ?, status) WHERE id=?')
                ->execute([
                    $email,
                    $phone,
                    $emailChanged ? 1 : 0,
                    $phoneChanged ? 1 : 0,
                    ($mustVerifyEmail || $mustVerifyPhone) ? 1 : 0,
                    'pending_verification',
                    $userId,
                ]);
            $pdo->prepare('UPDATE tbl_student_biodata SET phone=? WHERE user_id=?')->execute([$phone, $userId]);
            \audit_log($userId, 'account.contact_updated', 'user', $userId);
        });

        if ($mustVerifyEmail || $mustVerifyPhone) {
            $channel = $mustVerifyEmail ? 'email' : 'sms';
            session_regenerate_id(true);
            unset($_SESSION['_csrf']);
            $_SESSION['pending_verification_user_id'] = $userId;
            try {
                $this->otpService->issue($userId, $channel);
                $notice = 'Contact details saved. Verify your updated ' . ($channel === 'email' ? 'email address' : 'mobile number') . ' to reactivate your account.';
            } catch (ProviderException $exception) {
                $notice = 'Contact details saved, but the verification message could not be sent. ' . $exception->getMessage() . ' Use the resend option to try again.';
            }
            return ['page' => $channel === 'email' ? 'verify_email' : 'verify_phone', 'notice' => $notice];
        }

        $freshAccount = \db()->prepare('SELECT full_name, email, phone_number, role FROM tbl_users WHERE id=?');
        $freshAccount->execute([$userId]);
        return [
            'page' => 'account_settings',
            'notice' => 'Contact information updated successfully.',
            'viewData' => ['settingsAccount' => $freshAccount->fetch()],
        ];
    }

    private function updatePassword(): array
    {
        \require_login();
        $userId = (int) \auth_user()['id'];
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['confirm_password'] ?? '');
        if (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
            throw new RuntimeException('Your new password must be between 12 and 72 bytes.');
        }
        if (!hash_equals($newPassword, $confirmation)) {
            throw new RuntimeException('The new password and confirmation do not match.');
        }
        $query = \db()->prepare('SELECT password_hash FROM tbl_users WHERE id=?');
        $query->execute([$userId]);
        $passwordHash = $query->fetchColumn();
        if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
            throw new RuntimeException('Your current password is incorrect.');
        }

        \db_transaction(function (\PDO $pdo) use ($newPassword, $userId): void {
            $pdo->prepare('UPDATE tbl_users SET password_hash=? WHERE id=?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            \audit_log($userId, 'account.password_updated', 'user', $userId);
        });
        return ['page' => 'account_settings', 'notice' => 'Password changed successfully.'];
    }
}
