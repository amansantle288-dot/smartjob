<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Training\Controllers\TrainingController;

foreach (['add_training', 'verify_training', 'add_certification'] as $action) {
    if (!TrainingController::supports($action)) {
        throw new RuntimeException("Training action is not registered: {$action}");
    }
}

foreach (['upload_credential', 'save_biodata', 'login'] as $action) {
    if (TrainingController::supports($action)) {
        throw new RuntimeException("Action is assigned to the wrong module: {$action}");
    }
}

$pdo = db();
$pdo->beginTransaction();
$controller = new TrainingController();
$token = bin2hex(random_bytes(6));

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Training Test', 'training-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $_SESSION['user_id'] = $userId;

    $_POST = [
        'training_title' => 'Database Integration Test Course',
        'provider' => 'SMARTJOB Tests',
        'training_category' => 'Software',
        'training_description' => 'Transactional training controller test.',
        'duration_hours' => '4.5',
        'training_start_date' => '2026-01-01',
        'training_completion_date' => '2026-01-02',
        'acquired_skills' => 'PHP, MySQL, PHP',
    ];
    $result = $controller->handle('add_training');
    if ($result !== ['page' => 'training', 'notice' => 'Training record saved as self-reported.']) {
        throw new RuntimeException('Unexpected response from add_training.');
    }

    $_POST = [
        'certification_title' => 'Database Test Certificate',
        'issuer' => 'SMARTJOB Tests',
        'credential_number' => 'TEST-' . $token,
        'certification_issued_at' => '2026-01-02',
        'certification_expires_at' => '',
    ];
    $result = $controller->handle('add_certification');
    if ($result !== ['page' => 'training', 'notice' => 'Certification record saved as self-reported.']) {
        throw new RuntimeException('Unexpected response from add_certification.');
    }

    $training = $pdo->query("SELECT verification_status, duration_hours, acquired_skills FROM tbl_student_trainings WHERE user_id = {$userId}")->fetch();
    if (!$training || $training['verification_status'] !== 'self_reported' || (float) $training['duration_hours'] !== 4.5 || json_decode($training['acquired_skills'], true, 512, JSON_THROW_ON_ERROR) !== ['PHP', 'MySQL']) {
        throw new RuntimeException('Training data was not validated and persisted as expected.');
    }
    if ((int) $pdo->query("SELECT COUNT(*) FROM tbl_student_certifications WHERE user_id = {$userId} AND verification_status = 'self_reported'")->fetchColumn() !== 1) {
        throw new RuntimeException('Certification was not persisted as self-reported.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "Training and certification controller database operations verified.\n";
