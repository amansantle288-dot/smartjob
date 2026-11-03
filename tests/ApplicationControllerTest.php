<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Applications\Controllers\ApplicationController;

foreach (['apply', 'update_biodata_sharing', 'review_qualifications', 'update_application', 'withdraw_application'] as $action) {
    if (!ApplicationController::supports($action)) {
        throw new RuntimeException("Application action is not registered: {$action}");
    }
}

$pdo = db();
$token = bin2hex(random_bytes(6));
$studentId = 0;
$otherStudentId = 0;
$employerId = 0;
$companyId = 0;
$internshipId = 0;
$expiredInternshipId = 0;
$letterInternshipId = 0;
$applicationId = 0;
$letterApplicationId = 0;
$otherApplicationId = 0;
$credentialId = 0;
$ownedCredentialId = 0;

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Application Test', 'application-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $studentId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Other Student Test', 'application-other-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $otherStudentId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')")
        ->execute(['SMARTJOB Application Employer Test', 'application-employer-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $employerId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'approved')")
        ->execute(['SMARTJOB Application Test Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")
        ->execute([$companyId, $employerId]);
    $deadline = date('Y-m-d', strtotime('+1 day'));
    $pdo->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, description, slots, application_deadline, status) VALUES (?, ?, ?, ?, ?, ?, 'published')")
        ->execute([$companyId, $studentId, 'Application Test Internship ' . $token, 'Transactional application test.', 2, $deadline]);
    $internshipId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, description, slots, application_deadline, status) VALUES (?, ?, ?, ?, ?, ?, 'published')")
        ->execute([$companyId, $studentId, 'Application Letter Test Internship ' . $token, 'Optional application letter test.', 2, $deadline]);
    $letterInternshipId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, description, slots, application_deadline, status) VALUES (?, ?, ?, ?, ?, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'published')")
        ->execute([$companyId, $studentId, 'Expired Application Test ' . $token, 'Expired opportunities must reject new applications.', 2]);
    $expiredInternshipId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO tbl_student_credentials (user_id, category, original_name, storage_key, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$otherStudentId, 'resume', 'other-student.pdf', bin2hex(random_bytes(32)), 'application/pdf', 1]);
    $credentialId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO tbl_applications (internship_id, student_id, status) VALUES (?, ?, 'submitted')")
        ->execute([$internshipId, $otherStudentId]);
    $otherApplicationId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $studentId;
    $controller = new ApplicationController();
    $_POST = ['internship_id' => $expiredInternshipId];
    try {
        $controller->handle('apply');
        throw new RuntimeException('An expired internship accepted a new application.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'This opportunity is no longer accepting applications.') {
            throw $exception;
        }
    }
    $expiredApplications = $pdo->prepare('SELECT COUNT(*) FROM tbl_applications WHERE internship_id=?');
    $expiredApplications->execute([$expiredInternshipId]);
    if ((int) $expiredApplications->fetchColumn() !== 0) {
        throw new RuntimeException('The expired application attempt created an application record.');
    }

    $_POST = ['internship_id' => $internshipId, 'credential_ids' => [$credentialId]];
    try {
        $controller->handle('apply');
        throw new RuntimeException('Application accepted a supporting credential owned by another student.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'One or more selected documents do not belong to your account.') {
            throw $exception;
        }
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM tbl_applications WHERE internship_id = ? AND student_id = ?');
    $count->execute([$internshipId, $studentId]);
    if ((int) $count->fetchColumn() !== 0) {
        throw new RuntimeException('Failed application left a partial application record.');
    }

    $_POST = ['internship_id' => $internshipId, 'share_biodata' => '1'];
    $result = $controller->handle('apply');
    if ($result !== ['page' => 'applications', 'notice' => 'Your application was submitted.']) {
        throw new RuntimeException('Unexpected response from apply.');
    }
    $companyNotification = $pdo->prepare("SELECT COUNT(*) FROM tbl_notifications WHERE user_id=? AND title='New application received' AND message LIKE ?");
    $companyNotification->execute([$employerId, '%Application Test Internship%']);
    if ((int) $companyNotification->fetchColumn() !== 1) {
        throw new RuntimeException('Company was not notified about the new application.');
    }
    $applicationQuery = $pdo->prepare('SELECT id FROM tbl_applications WHERE internship_id = ? AND student_id = ?');
    $applicationQuery->execute([$internshipId, $studentId]);
    $applicationId = (int) $applicationQuery->fetchColumn();
    $emptyLetter = $pdo->prepare('SELECT cover_letter FROM tbl_applications WHERE id=?');
    $emptyLetter->execute([$applicationId]);
    if ($emptyLetter->fetchColumn() !== null) {
        throw new RuntimeException('An empty application letter was not preserved as optional.');
    }
    $shared = $pdo->prepare('SELECT COUNT(*) FROM tbl_application_biodata WHERE application_id = ? AND student_id = ?');
    $shared->execute([$applicationId, $studentId]);
    if ((int) $shared->fetchColumn() !== 1) {
        throw new RuntimeException('Requested biodata sharing was not saved with the application.');
    }
    $_POST = ['internship_id' => $internshipId];
    $result = $controller->handle('apply');
    if ($result !== ['page' => 'applications', 'notice' => 'You have already applied for this internship. View it in My Applications.']) {
        throw new RuntimeException('Duplicate application was not reported with a user-friendly message.');
    }
    $_POST = ['internship_id' => $letterInternshipId, 'cover_letter' => 'I hope to learn and contribute to this team.'];
    $result = $controller->handle('apply');
    if ($result !== ['page' => 'applications', 'notice' => 'Your application was submitted.']) {
        throw new RuntimeException('Application with a letter did not return the expected response.');
    }
    $applicationQuery->execute([$letterInternshipId, $studentId]);
    $letterApplicationId = (int) $applicationQuery->fetchColumn();
    $storedLetter = $pdo->prepare('SELECT cover_letter FROM tbl_applications WHERE id=?');
    $storedLetter->execute([$letterApplicationId]);
    if ($storedLetter->fetchColumn() !== 'I hope to learn and contribute to this team.') {
        throw new RuntimeException('The application letter was not saved.');
    }

    $_POST = ['application_id' => $applicationId, 'decision' => 'revoke'];
    $result = $controller->handle('update_biodata_sharing');
    if ($result['notice'] !== 'e-Biodata sharing revoked.') {
        throw new RuntimeException('Unexpected response when revoking biodata sharing.');
    }
    $shared->execute([$applicationId, $studentId]);
    if ((int) $shared->fetchColumn() !== 0) {
        throw new RuntimeException('Biodata revocation did not remove the sharing consent.');
    }

    $_SESSION['user_id'] = $employerId;
    $_POST = ['application_id' => $applicationId, 'status' => 'accepted'];
    try {
        $controller->handle('update_application');
        throw new RuntimeException('Employer accepted an application before reviewing qualifications.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Complete and pass the applicant qualification review before accepting this application.') {
            throw $exception;
        }
    }

    $_POST = ['application_id' => $applicationId, 'assessment' => 'qualified', 'notes' => 'Reviewed the submitted qualifications.'];
    try {
        $controller->handle('review_qualifications');
        throw new RuntimeException('Employer completed a qualification review without shared documents.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'The applicant has not shared an e-Biodata or resume for this application yet.') {
            throw $exception;
        }
    }

    $pdo->prepare('INSERT INTO tbl_student_credentials (user_id, category, original_name, storage_key, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$studentId, 'resume', 'student-resume.pdf', bin2hex(random_bytes(32)), 'application/pdf', 1]);
    $ownedCredentialId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_application_documents (application_id, credential_id) VALUES (?, ?)')
        ->execute([$applicationId, $ownedCredentialId]);

    $_POST = ['application_id' => $applicationId, 'assessment' => 'qualified', 'notes' => 'Reviewed the submitted resume.', 'missing_qualifications' => ''];
    $result = $controller->handle('review_qualifications');
    if ($result['notice'] !== 'Applicant qualification review recorded.') {
        throw new RuntimeException('Qualification review did not return the expected response.');
    }
    $interviewAt = date('Y-m-d\TH:i', strtotime('+2 days'));
    $_POST = ['application_id' => $applicationId, 'status' => 'interview_scheduled', 'interview_at' => $interviewAt, 'interview_location' => 'https://meet.example.invalid/session'];
    $result = $controller->handle('update_application');
    if ($result['notice'] !== 'Application status updated.') {
        throw new RuntimeException('Interview scheduling did not return the expected response.');
    }
    $interview = $pdo->prepare('SELECT status, interview_at, interview_location FROM tbl_applications WHERE id=?');
    $interview->execute([$applicationId]);
    $interviewRecord = $interview->fetch();
    if (!$interviewRecord || $interviewRecord['status'] !== 'interview_scheduled' || $interviewRecord['interview_at'] !== str_replace('T', ' ', $interviewAt) . ':00' || $interviewRecord['interview_location'] !== 'https://meet.example.invalid/session') {
        throw new RuntimeException('Interview details were not persisted correctly.');
    }
    $_POST = ['application_id' => $applicationId, 'status' => 'accepted'];
    $result = $controller->handle('update_application');
    if ($result['notice'] !== 'Application status updated.') {
        throw new RuntimeException('Acceptance after a qualified review failed.');
    }
    $notification = $pdo->prepare("SELECT COUNT(*) FROM tbl_notifications WHERE user_id=? AND title='Application status updated' AND message LIKE ?");
    $notification->execute([$studentId, '%Application Test Internship%accepted%']);
    if ((int) $notification->fetchColumn() !== 1) {
        throw new RuntimeException('Applicant was not notified about the application status change.');
    }

    $_SESSION['user_id'] = $otherStudentId;
    $_POST = ['application_id' => $otherApplicationId];
    $result = $controller->handle('withdraw_application');
    if ($result['notice'] !== 'Your application was withdrawn.') {
        throw new RuntimeException('Student application withdrawal did not return the expected response.');
    }
    $withdrawn = $pdo->prepare('SELECT status FROM tbl_applications WHERE id=?');
    $withdrawn->execute([$otherApplicationId]);
    if ($withdrawn->fetchColumn() !== 'withdrawn') {
        throw new RuntimeException('Student application withdrawal was not persisted.');
    }

    $_SESSION['user_id'] = $studentId;
    $_POST = ['application_id' => $otherApplicationId, 'decision' => 'revoke'];
    try {
        $controller->handle('update_biodata_sharing');
        throw new RuntimeException('Student changed another student application sharing consent.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'You can only manage sharing for your own application.') {
            throw $exception;
        }
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $pdo->beginTransaction();
    try {
        if ($applicationId > 0) {
            $pdo->prepare('DELETE FROM tbl_application_status_history WHERE application_id = ?')->execute([$applicationId]);
            $pdo->prepare('DELETE FROM tbl_application_biodata WHERE application_id = ?')->execute([$applicationId]);
            $pdo->prepare('DELETE FROM tbl_applications WHERE id = ?')->execute([$applicationId]);
            $pdo->prepare('DELETE FROM tbl_student_credentials WHERE id=?')->execute([$ownedCredentialId]);
        }
        if ($letterApplicationId > 0) {
            $pdo->prepare('DELETE FROM tbl_application_status_history WHERE application_id=?')->execute([$letterApplicationId]);
            $pdo->prepare('DELETE FROM tbl_applications WHERE id=?')->execute([$letterApplicationId]);
        }
        if ($otherApplicationId > 0) {
            $pdo->prepare('DELETE FROM tbl_applications WHERE id = ?')->execute([$otherApplicationId]);
        }
        if ($internshipId > 0) {
            $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id = ?')->execute([$internshipId]);
        }
        if ($letterInternshipId > 0) {
            $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id = ?')->execute([$letterInternshipId]);
        }
        if ($expiredInternshipId > 0) {
            $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id = ?')->execute([$expiredInternshipId]);
        }
        if ($companyId > 0) {
            $pdo->prepare('DELETE FROM tbl_companies WHERE id = ?')->execute([$companyId]);
        }
        if ($credentialId > 0) {
            $pdo->prepare('DELETE FROM tbl_student_credentials WHERE id = ?')->execute([$credentialId]);
        }
        foreach ([$studentId, $otherStudentId, $employerId] as $userId) {
            if ($userId > 0) {
                $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('DELETE FROM tbl_users WHERE id = ?')->execute([$userId]);
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

echo "Application ownership, document sharing, interview scheduling, withdrawal, qualification review, and notifications verified.\n";
