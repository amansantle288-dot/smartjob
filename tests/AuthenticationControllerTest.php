<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Authentication\Controllers\AuthenticationController;

foreach (['setup_admin', 'register', 'login', 'logout'] as $action) {
    if (!AuthenticationController::supports($action)) {
        throw new RuntimeException("Authentication action is not registered: {$action}");
    }
}

if (AuthenticationController::supports('save_profile')) {
    throw new RuntimeException('Student profile actions must not be handled by the authentication module.');
}

$_POST = [
    'full_name' => 'SMARTJOB Test',
    'email' => 'smartjob-controller-test@example.invalid',
    'password' => 'A-valid-test-password-123',
    'role' => 'admin',
];

try {
    (new AuthenticationController())->handle('register');
    throw new RuntimeException('Registration accepted a disallowed account role.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'Select a valid account type.') {
        throw $exception;
    }
}

echo "Authentication action routing and role validation verified.\n";
