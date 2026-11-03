<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Models;

use PDO;

final class AccountVerificationModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function invalidateActive(int $userId, string $channel, string $purpose): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tbl_account_verifications SET invalidated_at = NOW() WHERE user_id = ? AND verification_channel = ? AND verification_purpose = ? AND verified_at IS NULL AND invalidated_at IS NULL'
        );
        $statement->execute([$userId, $channel, $purpose]);
    }

    public function create(int $userId, string $channel, string $purpose, string $hash, int $expiryMinutes, int $maxAttempts): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tbl_account_verifications (user_id, verification_channel, verification_purpose, otp_hash, expires_at, max_attempts) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)'
        );
        $statement->execute([$userId, $channel, $purpose, $hash, $expiryMinutes, $maxAttempts]);
        return (int) $this->pdo->lastInsertId();
    }
}
