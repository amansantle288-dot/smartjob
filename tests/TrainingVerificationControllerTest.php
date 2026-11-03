<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Training\Controllers\TrainingController;

$pdo = db();
$token = bin2hex(random_bytes(6));
$institutionId = 0;
$coordinatorId = 0;
$studentId = 0;
$credentialId = 0;
$trainingId = 0;

try {
    $pdo->prepare('INSERT INTO tbl_institutions (name) VALUES (?)')
        ->execute(['SMARTJOB Verification Test ' . $token]);
    $institutionId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'coordinator', 'active')")
        ->execute(['SMARTJOB Coordinator Test', 'coordinator-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $coordinatorId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_ojt_coordinators (user_id, institution_id, status) VALUES (?, ?, ?)')
        ->execute([$coordinatorId, $institutionId, 'active']);

    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Student Test', 'verify-student-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $studentId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_student_profiles (user_id, institution_id) VALUES (?, ?)')
        ->execute([$studentId, $institutionId]);

    $pdo->prepare('INSERT INTO tbl_student_credentials (user_id, category, original_name, storage_key, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$studentId, 'training_certificate', 'test-certificate.pdf', bin2hex(random_bytes(32)), 'application/pdf', 1]);
    $credentialId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_student_trainings (user_id, credential_id, title, verification_status) VALUES (?, ?, ?, 'pending')")
        ->execute([$studentId, $credentialId, 'Verification Test Training']);
    $trainingId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $coordinatorId;
    $_POST = ['training_id' => $trainingId, 'decision' => 'verified', 'notes' => 'Test evidence reviewed.'];
    $result = (new TrainingController())->handle('verify_training');
    if ($result !== ['page' => 'training', 'notice' => 'Training evidence review recorded.']) {
        throw new RuntimeException('Unexpected response from training verification.');
    }

    $status = $pdo->prepare('SELECT verification_status FROM tbl_student_trainings WHERE id = ?');
    $status->execute([$trainingId]);
    if ($status->fetchColumn() !== 'verified') {
        throw new RuntimeException('Training verification status was not updated.');
    }

    $review = $pdo->prepare('SELECT reviewer_id, status, notes FROM tbl_training_verifications WHERE training_id = ?');
    $review->execute([$trainingId]);
    $reviewRecord = $review->fetch();
    if (!$reviewRecord || (int) $reviewRecord['reviewer_id'] !== $coordinatorId || $reviewRecord['status'] !== 'verified' || $reviewRecord['notes'] !== 'Test evidence reviewed.') {
        throw new RuntimeException('Training verification history was not recorded correctly.');
    }

    $notification = $pdo->prepare("SELECT COUNT(*) FROM tbl_notifications WHERE user_id = ? AND title = 'Training record reviewed'");
    $notification->execute([$studentId]);
    if ((int) $notification->fetchColumn() !== 1) {
        throw new RuntimeException('Training review did not notify the student.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $pdo->beginTransaction();
    try {
        if ($trainingId > 0) {
            $pdo->prepare('DELETE FROM tbl_training_verifications WHERE training_id = ?')->execute([$trainingId]);
            $pdo->prepare('DELETE FROM tbl_student_trainings WHERE id = ?')->execute([$trainingId]);
        }
        if ($studentId > 0) {
            $pdo->prepare('DELETE FROM tbl_notifications WHERE user_id = ?')->execute([$studentId]);
            $pdo->prepare('DELETE FROM tbl_student_credentials WHERE user_id = ?')->execute([$studentId]);
            $pdo->prepare('DELETE FROM tbl_student_profiles WHERE user_id = ?')->execute([$studentId]);
            $pdo->prepare('DELETE FROM tbl_users WHERE id = ?')->execute([$studentId]);
        }
        if ($coordinatorId > 0) {
            $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id = ?')->execute([$coordinatorId]);
            $pdo->prepare('DELETE FROM tbl_ojt_coordinators WHERE user_id = ?')->execute([$coordinatorId]);
            $pdo->prepare('DELETE FROM tbl_users WHERE id = ?')->execute([$coordinatorId]);
        }
        if ($institutionId > 0) {
            $pdo->prepare('DELETE FROM tbl_institutions WHERE id = ?')->execute([$institutionId]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

echo "Coordinator training verification and authorization path verified.\n";
