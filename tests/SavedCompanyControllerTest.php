<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\SavedCompanies\Controllers\SavedCompanyController;

if (!SavedCompanyController::supports('save_company') || !SavedCompanyController::supports('unsave_company')) {
    throw new RuntimeException('Saved company actions are not registered.');
}

$pdo = db();
$token = bin2hex(random_bytes(6));
$studentId = 0;
$otherStudentId = 0;
$companyId = 0;
$pendingCompanyId = 0;

try {
    foreach (['saved-company-' . $token, 'saved-company-other-' . $token] as $index => $emailPrefix) {
        $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
            ->execute(['SMARTJOB Saved Company Student ' . $index, $emailPrefix . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
        if ($index === 0) {
            $studentId = (int) $pdo->lastInsertId();
        } else {
            $otherStudentId = (int) $pdo->lastInsertId();
        }
    }

    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'approved')")
        ->execute(['SMARTJOB Saved Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'pending')")
        ->execute(['SMARTJOB Pending Saved Company ' . $token]);
    $pendingCompanyId = (int) $pdo->lastInsertId();

    $controller = new SavedCompanyController();
    $_SESSION['user_id'] = $studentId;
    $_POST = ['company_id' => $companyId, 'return_page' => 'explore'];
    $result = $controller->handle('save_company');
    if ($result !== ['page' => 'explore', 'notice' => 'Company saved to your interested companies.']) {
        throw new RuntimeException('Unexpected response from save_company.');
    }

    $savedCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_saved_companies WHERE student_id=? AND company_id=?');
    $savedCount->execute([$studentId, $companyId]);
    if ((int) $savedCount->fetchColumn() !== 1) {
        throw new RuntimeException('Interested company was not persisted.');
    }
    $_POST = ['company_id' => $companyId, 'return_page' => 'saved_companies'];
    $controller->handle('save_company');
    $savedCount->execute([$studentId, $companyId]);
    if ((int) $savedCount->fetchColumn() !== 1) {
        throw new RuntimeException('Saving the same company created duplicate records.');
    }

    $_SESSION['user_id'] = $otherStudentId;
    $_POST = ['company_id' => $companyId, 'return_page' => 'saved_companies'];
    $controller->handle('unsave_company');
    $savedCount->execute([$studentId, $companyId]);
    if ((int) $savedCount->fetchColumn() !== 1) {
        throw new RuntimeException('One student removed another student\'s saved company.');
    }

    $_SESSION['user_id'] = $studentId;
    $_POST = ['company_id' => $pendingCompanyId, 'return_page' => 'explore'];
    try {
        $controller->handle('save_company');
        throw new RuntimeException('An unverified company was added to the interested list.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'This company profile is no longer available to save.') {
            throw $exception;
        }
    }

    $_POST = ['company_id' => $companyId, 'return_page' => 'saved_companies'];
    $result = $controller->handle('unsave_company');
    if ($result !== ['page' => 'saved_companies', 'notice' => 'Company removed from your interested companies.']) {
        throw new RuntimeException('Unexpected response from unsave_company.');
    }
    $savedCount->execute([$studentId, $companyId]);
    if ((int) $savedCount->fetchColumn() !== 0) {
        throw new RuntimeException('Interested company was not removed.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $pdo->beginTransaction();
    try {
        foreach ([$studentId, $otherStudentId] as $userId) {
            if ($userId > 0) {
                $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id=?')->execute([$userId]);
            }
        }
        if ($companyId > 0) {
            $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
        }
        if ($pendingCompanyId > 0) {
            $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$pendingCompanyId]);
        }
        foreach ([$studentId, $otherStudentId] as $userId) {
            if ($userId > 0) {
                $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$userId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

echo "Interested company persistence and ownership verified.\n";
