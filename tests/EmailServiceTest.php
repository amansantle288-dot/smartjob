<?php
declare(strict_types=1);

require __DIR__ . '/../app/services/EmailService.php';

foreach (['MAIL_PROVIDER', 'SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'MAIL_FROM_ADDRESS'] as $name) {
    putenv($name);
}

try {
    EmailService::send('nobody@example.invalid', 'SMARTJOB test', 'This message must never be sent.');
    fwrite(STDERR, "Expected unconfigured email to remain a draft.\n");
    exit(1);
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'The email remains a draft.')) {
        fwrite(STDERR, "Unexpected email configuration failure: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "Unconfigured SMTP safely leaves drafts unsent.\n";
