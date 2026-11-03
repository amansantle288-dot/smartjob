<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SmartMail\Services;

interface EmailProviderInterface
{
    /** @return array{message_id: string|null, http_status: int} */
    public function send(string $recipient, string $subject, string $text, string $html): array;
}
