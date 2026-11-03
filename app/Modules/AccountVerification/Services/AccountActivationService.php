<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Services;

use PDO;

final class AccountActivationService
{
    public static function refresh(PDO $pdo, int $userId): bool
    {
        $statement = $pdo->prepare('SELECT status, verification_required, phone_verification_required, email_verified_at, phone_verified_at FROM tbl_users WHERE id = ? FOR UPDATE');
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user || $user['status'] !== 'pending_verification') {
            return false;
        }

        $emailSatisfied = !(bool) $user['verification_required'] || $user['email_verified_at'] !== null;
        $phoneSatisfied = !(bool) $user['phone_verification_required'] || $user['phone_verified_at'] !== null;
        if (!$emailSatisfied || !$phoneSatisfied) {
            return false;
        }

        $pdo->prepare("UPDATE tbl_users SET status = 'active' WHERE id = ?")->execute([$userId]);
        return true;
    }
}
