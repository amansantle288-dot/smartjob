<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Internship\Controllers\InternshipController;

if (!InternshipController::supports('create_internship') || !InternshipController::supports('update_internship') || !InternshipController::supports('delete_internship') || InternshipController::supports('apply')) {
    throw new RuntimeException('Internship action routing is incorrect.');
}

$pdo = db();
$token = bin2hex(random_bytes(6));
$employerId = 0;
$companyId = 0;
$internshipId = 0;

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')")
        ->execute(['SMARTJOB Internship Test', 'internship-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $employerId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'pending')")
        ->execute(['SMARTJOB Internship Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")
        ->execute([$companyId, $employerId]);
    $_SESSION['user_id'] = $employerId;
    $controller = new InternshipController();
    $_POST = [
        'title' => 'Blocked Internship Test',
        'description' => 'A pending company must not publish.',
        'slots' => '1',
        'hiring_duration_preset' => '30',
    ];
    try {
        $controller->handle('create_internship');
        throw new RuntimeException('A pending company published an internship.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Your company must be approved before publishing internships.') {
            throw $exception;
        }
    }

    $pdo->prepare("UPDATE tbl_companies SET verification_status = 'approved' WHERE id = ?")->execute([$companyId]);
    ob_start();
    render_page('internships', null, []);
    $internshipsHtml = (string) ob_get_clean();
    if (!str_contains($internshipsHtml, 'id="create-internship"') || !str_contains($internshipsHtml, 'name="hiring_duration_preset"') || str_contains($internshipsHtml, 'name="company_logo"') || str_contains($internshipsHtml, 'type="file"')) {
        throw new RuntimeException('The approved employer internship workflow is missing its create form or includes a logo upload.');
    }

    $_POST = [
        'title' => 'Invalid Duration Test',
        'description' => 'Invalid durations must be rejected.',
        'slots' => '1',
        'hiring_duration_preset' => 'custom',
        'custom_hiring_duration_days' => '0',
    ];
    try {
        $controller->handle('create_internship');
        throw new RuntimeException('A zero-day hiring duration was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Choose a custom hiring duration from 1 to 90 days.') {
            throw $exception;
        }
    }

    $_POST = [
        'title' => 'Approved Internship Test',
        'description' => 'An approved company can publish.',
        'location' => 'Test City',
        'slots' => '2',
        'hiring_duration_preset' => '15',
    ];
    $result = $controller->handle('create_internship');
    if ($result !== ['page' => 'internships', 'notice' => 'Internship published.']) {
        throw new RuntimeException('Unexpected response from create_internship.');
    }

    $internship = $pdo->prepare('SELECT created_by, status, published_at, hiring_duration_days, application_deadline FROM tbl_internship_opportunities WHERE company_id = ? AND title = ?');
    $internship->execute([$companyId, 'Approved Internship Test']);
    $record = $internship->fetch();
    if (!$record || (int) $record['created_by'] !== $employerId || $record['status'] !== 'published' || (int) $record['hiring_duration_days'] !== 15 || $record['application_deadline'] !== HiringCountdown::deadlineFromDuration((string) $record['published_at'], 15)) {
        throw new RuntimeException('Published internship was not saved correctly.');
    }
    $originalPublishedAt = (string) $record['published_at'];
    $idQuery = $pdo->prepare('SELECT id FROM tbl_internship_opportunities WHERE company_id = ? AND title = ?');
    $idQuery->execute([$companyId, 'Approved Internship Test']);
    $internshipId = (int) $idQuery->fetchColumn();

    $_POST = [
        'internship_id' => $internshipId,
        'title' => 'Updated Internship Test',
        'category' => 'Software development',
        'description' => 'Updated role details.',
        'requirements' => 'PHP and teamwork.',
        'location' => 'Updated City',
        'work_mode' => 'remote',
        'slots' => '4',
        'hiring_duration_preset' => '30',
        'custom_hiring_duration_days' => '30',
        'posting_status' => 'published',
    ];
    $result = $controller->handle('update_internship');
    if ($result !== ['page' => 'internships', 'notice' => 'Internship updated.']) {
        throw new RuntimeException('Unexpected response from update_internship.');
    }
    $updated = $pdo->prepare('SELECT title, category, description, requirements, location, work_mode, slots, application_deadline, published_at, hiring_duration_days, status FROM tbl_internship_opportunities WHERE id=? AND company_id=?');
    $updated->execute([$internshipId, $companyId]);
    $updatedRecord = $updated->fetch();
    $updatedDeadline = HiringCountdown::deadlineFromDuration($originalPublishedAt, 30);
    if (!$updatedRecord || $updatedRecord['title'] !== 'Updated Internship Test' || $updatedRecord['category'] !== 'Software development' || $updatedRecord['description'] !== 'Updated role details.' || $updatedRecord['requirements'] !== 'PHP and teamwork.' || $updatedRecord['location'] !== 'Updated City' || $updatedRecord['work_mode'] !== 'remote' || (int) $updatedRecord['slots'] !== 4 || (int) $updatedRecord['hiring_duration_days'] !== 30 || $updatedRecord['published_at'] !== $originalPublishedAt || $updatedRecord['application_deadline'] !== $updatedDeadline || $updatedRecord['status'] !== 'published') {
        throw new RuntimeException('Internship edits were not saved correctly.');
    }

    $_POST = ['internship_id' => $internshipId];
    $result = $controller->handle('delete_internship');
    if ($result !== ['page' => 'internships', 'notice' => 'Internship closed and no longer accepting applications.']) {
        throw new RuntimeException('Unexpected response from delete_internship.');
    }
    $closed = $pdo->prepare('SELECT status, application_deadline FROM tbl_internship_opportunities WHERE id=?');
    $closed->execute([$internshipId]);
    $closedRecord = $closed->fetch();
    if (!$closedRecord || $closedRecord['status'] !== 'closed' || $closedRecord['application_deadline'] !== $updatedDeadline) {
        throw new RuntimeException('Closing the internship changed its deadline or failed to close it.');
    }
    $pdo->prepare('UPDATE tbl_internship_opportunities SET application_deadline=DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id=?')->execute([$internshipId]);

    $_POST = [
        'internship_id' => $internshipId,
        'title' => 'Updated Internship Test',
        'description' => 'Updated role details.',
        'slots' => '4',
        'hiring_duration_preset' => '60',
        'posting_status' => 'published',
    ];
    $controller->handle('update_internship');
    $reopened = $pdo->prepare('SELECT status, published_at, hiring_duration_days, application_deadline FROM tbl_internship_opportunities WHERE id=?');
    $reopened->execute([$internshipId]);
    $reopenedRecord = $reopened->fetch();
    if (!$reopenedRecord || $reopenedRecord['status'] !== 'published' || $reopenedRecord['published_at'] !== $originalPublishedAt || (int) $reopenedRecord['hiring_duration_days'] !== 60 || $reopenedRecord['application_deadline'] !== HiringCountdown::deadlineFromDuration(HiringCountdown::today(), 60)) {
        throw new RuntimeException('An employer could not explicitly reopen the internship while preserving its original posting date.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $pdo->beginTransaction();
    try {
        if ($employerId > 0) {
            $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id = ?')->execute([$employerId]);
        }
        if ($internshipId > 0) {
            $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id = ?')->execute([$internshipId]);
        }
        if ($employerId > 0) {
            $pdo->prepare('DELETE FROM tbl_company_members WHERE user_id = ?')->execute([$employerId]);
        }
        if ($companyId > 0) {
            $pdo->prepare('DELETE FROM tbl_companies WHERE id = ?')->execute([$companyId]);
        }
        if ($employerId > 0) {
            $pdo->prepare('DELETE FROM tbl_users WHERE id = ?')->execute([$employerId]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

echo "Internship authorization, publishing, editing, and closing verified.\n";
