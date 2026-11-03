<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\AccountVerification\Services\ProviderException;
use SMARTJOB\Modules\SmartMail\Services\MailjetEmailProvider;

putenv('MAILJET_API_KEY=test-api-key');
putenv('MAILJET_SECRET_KEY');
putenv('MAIL_FROM_ADDRESS=sender@example.com');

try {
    (new MailjetEmailProvider())->send('recipient@example.com', 'Test', 'Text', '<p>HTML</p>');
    throw new RuntimeException('Mailjet sent without its required secret key.');
} catch (ProviderException $exception) {
    if ($exception->providerCode !== 'CONFIGURATION') {
        throw $exception;
    }
}

echo "Mailjet configuration guard passed.\n";
