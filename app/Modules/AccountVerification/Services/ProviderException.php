<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Services;

use RuntimeException;

final class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerCode,
        public readonly ?int $httpStatus = null
    ) {
        parent::__construct($message);
    }
}
