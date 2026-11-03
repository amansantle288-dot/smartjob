<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Placements\Controllers\PlacementController;

if (!PlacementController::supports('approve_placement')) {
    throw new RuntimeException('Placement approval action is not registered.');
}

$pdo = db();
$token = bin2hex(random_bytes(6));
$studentId = 0;
$employerId = 0;
$coordinatorId = 0;
$otherCoordinatorId = 0;
$institutionId = 0;
$otherInstitutionId = 0;
$companyId = 0;
$internshipId = 0;
$applicationId = 0;
$placementId = 0;

try {
    $userInsert = $pdo->prepare('INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, \'active\')');
    $userInsert->execute(['SMARTJOB Placement Student', 'placement-student-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), 'student']);
    $studentId = (int) $pdo->lastInsertId();
    $userInsert->execute(['SMARTJOB Placement Employer', 'placement-employer-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), 'employer']);
    $employerId = (int) $pdo->lastInsertId();
    $userInsert->execute(['SMARTJOB Placement Coordinator', 'placement-coordinator-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), 'coordinator']);
    $coordinatorId = (int) $pdo->lastInsertId();
    $userInsert->execute(['SMARTJOB Other Placement Coordinator', 'placement-other-coordinator-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), 'coordinator']);
    $otherCoordinatorId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO tbl_institutions (name, code) VALUES (?, ?)')->execute(['SMARTJOB Placement Institution ' . $token, 'P' . $token]);
    $institutionId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_institutions (name, code) VALUES (?, ?)')->execute(['SMARTJOB Other Institution ' . $token, 'O' . $token]);
    $otherInstitutionId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_ojt_coordinators (user_id, institution_id, status) VALUES (?, ?, \'active\')')->execute([$coordinatorId, $institutionId]);
    $pdo->prepare('INSERT INTO tbl_ojt_coordinators (user_id, institution_id, status) VALUES (?, ?, \'active\')')->execute([$otherCoordinatorId, $otherInstitutionId]);
    $pdo->prepare('INSERT INTO tbl_student_profiles (user_id, institution_id, program) VALUES (?, ?, ?)')->execute([$studentId, $institutionId, 'Information Technology']);

    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'approved')")->execute(['SMARTJOB Placement Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")->execute([$companyId, $employerId]);
    $pdo->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, description, slots, application_deadline, status) VALUES (?, ?, ?, ?, 1, ?, 'published')")
        ->execute([$companyId, $employerId, 'Placement Test Opportunity ' . $token, 'Placement approval integration test.', date('Y-m-d', strtotime('+1 day'))]);
    $internshipId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_applications (internship_id, student_id, status) VALUES (?, ?, 'accepted')")->execute([$internshipId, $studentId]);
    $applicationId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_placements (application_id, coordinator_id, status) VALUES (?, ?, 'pending')")->execute([$applicationId, $coordinatorId]);
    $placementId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $otherCoordinatorId;
    $_POST = ['placement_id' => $placementId, 'start_date' => date('Y-m-d', strtotime('+1 day')), 'end_date' => date('Y-m-d', strtotime('+90 days')), 'required_hours' => '240'];
    try {
        (new PlacementController())->handle('approve_placement');
        throw new RuntimeException('A coordinator accessed a placement outside their institution.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Placement not found in your assigned institution.') {
            throw $exception;
        }
    }

    $_SESSION['user_id'] = $coordinatorId;
    $result = (new PlacementController())->handle('approve_placement');
    if ($result['notice'] !== 'Placement approved and activated.') {
        throw new RuntimeException('Placement approval did not return the expected response.');
    }
    $placementQuery = $pdo->prepare('SELECT status, coordinator_id, start_date, end_date, required_hours FROM tbl_placements WHERE id=?');
    $placementQuery->execute([$placementId]);
    $placement = $placementQuery->fetch();
    if (!$placement || $placement['status'] !== 'active' || (int) $placement['coordinator_id'] !== $coordinatorId || (int) $placement['required_hours'] !== 240) {
        throw new RuntimeException('Placement approval did not persist its coordinator and hour requirements.');
    }
    $notification = $pdo->prepare("SELECT COUNT(*) FROM tbl_notifications WHERE user_id=? AND title='Placement approved' AND related_id=?");
    $notification->execute([$studentId, $applicationId]);
    if ((int) $notification->fetchColumn() !== 1) {
        throw new RuntimeException('The student was not notified of placement approval.');
    }
    ob_start();
    render_page('placements', null, []);
    $html = (string) ob_get_clean();
    if (!str_contains($html, 'Placement approvals') || !str_contains($html, 'Placement Test Opportunity')) {
        throw new RuntimeException('The coordinator placement workspace did not render its active placement.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    if ($placementId) {
        $pdo->prepare('DELETE FROM tbl_placements WHERE id=?')->execute([$placementId]);
    }
    if ($applicationId) {
        $pdo->prepare('DELETE FROM tbl_applications WHERE id=?')->execute([$applicationId]);
    }
    if ($internshipId) {
        $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id=?')->execute([$internshipId]);
    }
    if ($companyId) {
        $pdo->prepare('DELETE FROM tbl_company_members WHERE company_id=?')->execute([$companyId]);
        $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
    }
    foreach ([$studentId, $employerId, $coordinatorId, $otherCoordinatorId] as $userId) {
        if ($userId) {
            $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id=?')->execute([$userId]);
            $pdo->prepare('DELETE FROM tbl_notifications WHERE user_id=?')->execute([$userId]);
        }
    }
    foreach ([$studentId, $employerId, $coordinatorId, $otherCoordinatorId] as $userId) {
        if ($userId) {
            $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$userId]);
        }
    }
    foreach ([$institutionId, $otherInstitutionId] as $id) {
        if ($id) {
            $pdo->prepare('DELETE FROM tbl_institutions WHERE id=?')->execute([$id]);
        }
    }
}

echo "Placement approval is institution-scoped and notifies the student.\n";
