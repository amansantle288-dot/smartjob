<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\SavedInternships\Controllers\SavedInternshipController;

if (!SavedInternshipController::supports('save_internship') || !SavedInternshipController::supports('unsave_internship')) {
    throw new RuntimeException('Saved internship actions are not registered.');
}

$pdo = db();
$token = bin2hex(random_bytes(6));
$studentId = 0;
$companyId = 0;
$internshipId = 0;

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Saved Internship Test', 'saved-internship-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $studentId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'approved')")
        ->execute(['SMARTJOB Saved Internship Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, description, slots, application_deadline, status) VALUES (?, ?, ?, ?, 1, ?, 'published')")
        ->execute([$companyId, $studentId, 'Saved Internship ' . $token, 'Saved internship controller test.', date('Y-m-d', strtotime('+1 day'))]);
    $internshipId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $studentId;
    $_POST = ['internship_id' => $internshipId, 'return_page' => 'explore'];
    $controller = new SavedInternshipController();
    $result = $controller->handle('save_internship');
    if ($result !== ['page' => 'explore', 'notice' => 'Internship saved to your list.']) {
        throw new RuntimeException('Unexpected response from save_internship.');
    }

    $savedCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_saved_internships WHERE student_id=? AND internship_id=?');
    $savedCount->execute([$studentId, $internshipId]);
    if ((int) $savedCount->fetchColumn() !== 1) {
        throw new RuntimeException('Saved internship was not persisted.');
    }
    $controller->handle('save_internship');
    $savedCount->execute([$studentId, $internshipId]);
    if ((int) $savedCount->fetchColumn() !== 1) {
        throw new RuntimeException('Saving the same internship created duplicate rows.');
    }

    $_POST = ['internship_id' => $internshipId, 'return_page' => 'saved'];
    $result = $controller->handle('unsave_internship');
    if ($result !== ['page' => 'saved', 'notice' => 'Internship removed from your saved list.']) {
        throw new RuntimeException('Unexpected response from unsave_internship.');
    }
    $savedCount->execute([$studentId, $internshipId]);
    if ((int) $savedCount->fetchColumn() !== 0) {
        throw new RuntimeException('Saved internship was not removed.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $pdo->beginTransaction();
    try {
        if ($studentId > 0) {
            $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id=?')->execute([$studentId]);
        }
        if ($internshipId > 0) {
            $pdo->prepare('DELETE FROM tbl_saved_internships WHERE internship_id=?')->execute([$internshipId]);
            $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id=?')->execute([$internshipId]);
        }
        if ($companyId > 0) {
            $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
        }
        if ($studentId > 0) {
            $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$studentId]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

echo "Saved internship persistence verified.\n";
