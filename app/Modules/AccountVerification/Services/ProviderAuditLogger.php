<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\AccountVerification\Services;

use PDO;

final class ProviderAuditLogger
{
    public static function record(PDO $pdo, string $provider, string $service, string $status, ?int $httpStatus, ?string $errorCode): void
    {
        $reference = sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            random_int(0, 0xffffffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffffffffffff)
        );
        $pdo->prepare(
            'INSERT INTO tbl_api_provider_logs (provider_name, service_type, request_reference, response_status, http_status_code, error_code) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$provider, $service, $reference, $status, $httpStatus, $errorCode]);
    }
}
