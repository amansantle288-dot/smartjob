<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmsGateway\Services;

interface SmsProviderInterface
{
    /** @return array{message_id: string|null, http_status: int} */
    public function send(string $recipient, string $message): array;
}
