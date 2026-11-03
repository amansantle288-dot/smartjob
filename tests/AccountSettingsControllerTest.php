<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Authentication\Controllers\AccountSettingsController;

$pdo = db();
$token = bin2hex(random_bytes(6));
$userId = 0;
$adminId = 0;
$oldEmail = 'account-settings-' . $token . '@example.invalid';
$newEmail = 'account-settings-updated-' . $token . '@example.invalid';
$oldPassword = 'SMARTJOB-test-password-123';
$newPassword = 'SMARTJOB-new-password-456';
$adminNewPassword = 'SMARTJOB-admin-password-789';

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, phone_number, password_hash, role, status, email_verified_at, phone_verified_at) VALUES (?, ?, ?, ?, 'student', 'active', NOW(), NOW())")
        ->execute(['SMARTJOB Account Settings Test', $oldEmail, '+639171234567', password_hash($oldPassword, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_student_profiles (user_id) VALUES (?)')->execute([$userId]);
    $pdo->prepare('INSERT INTO tbl_student_biodata (user_id, phone) VALUES (?, ?)')->execute([$userId, '+639171234567']);
    $_SESSION['user_id'] = $userId;
    $controller = new AccountSettingsController();

    $_POST = ['email' => $newEmail, 'phone_number' => '09181234567', 'current_password' => 'incorrect-current-password'];
    try {
        $controller->handle('update_account_contacts');
        throw new RuntimeException('Contact change accepted an incorrect current password.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Your current password is incorrect.') {
            throw $exception;
        }
    }

    $_POST = ['email' => $newEmail, 'phone_number' => '09181234567', 'current_password' => $oldPassword];
    $contactsResult = $controller->handle('update_account_contacts');
    if (($contactsResult['page'] ?? '') !== 'account_settings' || ($contactsResult['notice'] ?? '') !== 'Contact information updated successfully.' || ($contactsResult['viewData']['settingsAccount']['email'] ?? '') !== $newEmail) {
        throw new RuntimeException('Unexpected contact update response.');
    }
    $account = $pdo->prepare('SELECT email, phone_number, email_verified_at, phone_verified_at FROM tbl_users WHERE id=?');
    $account->execute([$userId]);
    $accountRecord = $account->fetch();
    $biodataPhone = $pdo->prepare('SELECT phone FROM tbl_student_biodata WHERE user_id=?');
    $biodataPhone->execute([$userId]);
    if (!$accountRecord || $accountRecord['email'] !== $newEmail || $accountRecord['phone_number'] !== '+639181234567' || $accountRecord['email_verified_at'] !== null || $accountRecord['phone_verified_at'] !== null || $biodataPhone->fetchColumn() !== '+639181234567') {
        throw new RuntimeException('Contact information was not synchronized to the account and biodata.');
    }

    $_POST = ['email' => $newEmail, 'phone_number' => '+447911123456', 'current_password' => $oldPassword];
    try {
        $controller->handle('update_account_contacts');
        throw new RuntimeException('Contact update accepted a non-Philippine mobile number.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Enter a valid Philippine mobile number, such as +639XXXXXXXXX.') {
            throw $exception;
        }
    }

    $_POST = ['current_password' => 'incorrect-current-password', 'new_password' => $newPassword, 'confirm_password' => $newPassword];
    try {
        $controller->handle('update_account_password');
        throw new RuntimeException('Password change accepted an incorrect current password.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Your current password is incorrect.') {
            throw $exception;
        }
    }

    $_POST = ['current_password' => $oldPassword, 'new_password' => $newPassword, 'confirm_password' => $newPassword];
    $passwordResult = $controller->handle('update_account_password');
    $storedHash = $pdo->prepare('SELECT password_hash FROM tbl_users WHERE id=?');
    $storedHash->execute([$userId]);
    $newHash = $storedHash->fetchColumn();
    if ($passwordResult !== ['page' => 'account_settings', 'notice' => 'Password changed successfully.'] || !is_string($newHash) || !password_verify($newPassword, $newHash) || $newHash === $newPassword) {
        throw new RuntimeException('Password did not update using a secure password hash.');
    }

    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')")
        ->execute(['SMARTJOB Admin Settings Test', 'account-settings-admin-' . $token . '@example.invalid', password_hash($oldPassword, PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();
    $_SESSION['user_id'] = $adminId;
    $_POST = ['current_password' => $oldPassword, 'new_password' => $adminNewPassword, 'confirm_password' => $adminNewPassword];
    $adminPasswordResult = $controller->handle('update_account_password');
    $adminStoredHash = $pdo->prepare('SELECT password_hash FROM tbl_users WHERE id=?');
    $adminStoredHash->execute([$adminId]);
    $adminNewHash = $adminStoredHash->fetchColumn();
    if ($adminPasswordResult !== ['page' => 'account_settings', 'notice' => 'Password changed successfully.'] || !is_string($adminNewHash) || !password_verify($adminNewPassword, $adminNewHash)) {
        throw new RuntimeException('An administrator could not securely change their own password.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    if ($adminId > 0) {
        $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id=?')->execute([$adminId]);
        $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$adminId]);
    }
    if ($userId > 0) {
        $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id=?')->execute([$userId]);
        $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$userId]);
    }
}

echo "Account contact synchronization and password reauthentication verified.\n";
