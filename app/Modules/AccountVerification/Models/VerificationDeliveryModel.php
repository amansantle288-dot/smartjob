<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Models;

use PDO;

final class VerificationDeliveryModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(
        ?int $verificationId,
        int $userId,
        string $channel,
        string $provider,
        string $recipient,
        string $status,
        ?string $messageId = null,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): void {
        $this->pdo->prepare(
            'INSERT INTO tbl_verification_delivery_logs (account_verification_id, user_id, delivery_channel, provider_name, recipient_masked, provider_message_id, delivery_status, error_code, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $verificationId,
            $userId,
            $channel,
            $provider,
            $recipient,
            $messageId,
            $status,
            $errorCode,
            $errorMessage,
        ]);
    }
}
