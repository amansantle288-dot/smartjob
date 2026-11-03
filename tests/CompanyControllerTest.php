<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Company\Controllers\CompanyController;

if (!CompanyController::supports('review_company') || !CompanyController::supports('review_company_document') || !CompanyController::supports('upload_company_document') || !CompanyController::supports('upload_company_documents') || CompanyController::supports('create_internship')) {
    throw new RuntimeException('Company action routing is incorrect.');
}

$pdo = db();
$token = bin2hex(random_bytes(6));
$reviewerId = 0;
$employerId = 0;
$companyId = 0;
$documentId = 0;

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')")
        ->execute(['SMARTJOB Company Review Test', 'company-review-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $reviewerId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')")
        ->execute(['SMARTJOB Company Document Owner', 'company-document-owner-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $employerId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_companies (name, verification_status) VALUES (?, 'pending')")
        ->execute(['SMARTJOB Review Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")
        ->execute([$companyId, $employerId]);
    $pdo->prepare("INSERT INTO tbl_company_verifications (company_id, status) VALUES (?, 'pending')")
        ->execute([$companyId]);
    $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size) VALUES (?, ?, 'business_registration', 'registration.pdf', ?, 'application/pdf', 100)")
        ->execute([$companyId, $employerId, bin2hex(random_bytes(32))]);
    $documentId = (int) $pdo->lastInsertId();
    $requiredDocument = $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, review_status) VALUES (?, ?, ?, ?, ?, 'application/pdf', 100, 'approved')");
    foreach (['business_permit', 'bir_registration', 'other_business_registration'] as $documentType) {
        $requiredDocument->execute([$companyId, $employerId, $documentType, $documentType . '.pdf', bin2hex(random_bytes(32))]);
    }

    $_SESSION['user_id'] = $reviewerId;
    $_POST = ['document_id' => $documentId, 'decision' => 'under_review', 'notes' => 'Review started.'];
    $result = (new CompanyController())->handle('review_company_document');
    if ($result !== ['page' => 'companies', 'notice' => 'Company document review saved.']) {
        throw new RuntimeException('Unexpected response from document review.');
    }
    $_POST = ['company_id' => $companyId, 'decision' => 'approved', 'remarks' => 'Test approval.'];
    try {
        (new CompanyController())->handle('review_company');
        throw new RuntimeException('Company was verified before its document was approved.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Submit and obtain approval for all required documents: Business Registration.') {
            throw $exception;
        }
    }

    $_POST = ['document_id' => $documentId, 'decision' => 'revision_requested', 'notes' => 'Please provide a complete, readable copy.'];
    (new CompanyController())->handle('review_company_document');
    $documentReview = $pdo->prepare('SELECT review_status, reviewer_id, reviewer_notes FROM tbl_company_documents WHERE id=?');
    $documentReview->execute([$documentId]);
    $documentRecord = $documentReview->fetch();
    if (!$documentRecord || $documentRecord['review_status'] !== 'revision_requested' || (int) $documentRecord['reviewer_id'] !== $reviewerId || $documentRecord['reviewer_notes'] !== 'Please provide a complete, readable copy.') {
        throw new RuntimeException('Document return feedback was not recorded.');
    }
    $companyStatus = $pdo->prepare('SELECT verification_status FROM tbl_companies WHERE id=?');
    $companyStatus->execute([$companyId]);
    if ($companyStatus->fetchColumn() !== 'pending') {
        throw new RuntimeException('Returning a company document did not restrict the company account.');
    }

    $_POST = ['company_id' => $companyId, 'decision' => 'approved', 'remarks' => 'Test approval.'];
    try {
        (new CompanyController())->handle('review_company');
        throw new RuntimeException('Company was verified while a document remained returned for revision.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Submit and obtain approval for all required documents: Business Registration.') {
            throw $exception;
        }
    }

    $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, previous_document_id) VALUES (?, ?, 'business_registration', 'registration-revised.pdf', ?, 'application/pdf', 100, ?)")
        ->execute([$companyId, $employerId, bin2hex(random_bytes(32)), $documentId]);
    $replacementId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_document_review_history (document_id, action) VALUES (?, 'uploaded')")->execute([$replacementId]);
    $_POST = ['document_id' => $replacementId, 'decision' => 'approved', 'notes' => 'Replacement has been reviewed.'];
    (new CompanyController())->handle('review_company_document');

    $_POST = ['company_id' => $companyId, 'decision' => 'approved', 'remarks' => 'Test approval.'];
    $result = (new CompanyController())->handle('review_company');
    if ($result !== ['page' => 'companies', 'notice' => 'Company review saved.']) {
        throw new RuntimeException('Unexpected response from review_company.');
    }
    $company = $pdo->prepare('SELECT verification_status, verified_by FROM tbl_companies WHERE id = ?');
    $company->execute([$companyId]);
    $companyRecord = $company->fetch();
    if (!$companyRecord || $companyRecord['verification_status'] !== 'approved' || (int) $companyRecord['verified_by'] !== $reviewerId) {
        throw new RuntimeException('Company verification status was not updated.');
    }
    $log = $pdo->prepare('SELECT decision, notes FROM tbl_verification_logs WHERE company_id = ? AND reviewer_id = ? ORDER BY id DESC LIMIT 1');
    $log->execute([$companyId, $reviewerId]);
    $logRecord = $log->fetch();
    if (!$logRecord || $logRecord['decision'] !== 'approved' || $logRecord['notes'] !== 'Test approval.') {
        throw new RuntimeException('Company verification history was not recorded.');
    }

    $_POST = ['company_id' => $companyId, 'decision' => 'revision_requested', 'remarks' => 'Please add a company website.'];
    $result = (new CompanyController())->handle('review_company');
    if ($result !== ['page' => 'companies', 'notice' => 'Revision request sent to the company.']) {
        throw new RuntimeException('Unexpected response from revision request.');
    }
    $company->execute([$companyId]);
    $companyRecord = $company->fetch();
    if (!$companyRecord || $companyRecord['verification_status'] !== 'pending') {
        throw new RuntimeException('A revision request did not return the company to pending review.');
    }
    $verification = $pdo->prepare('SELECT status, reviewer_notes FROM tbl_company_verifications WHERE company_id=?');
    $verification->execute([$companyId]);
    $verificationRecord = $verification->fetch();
    if (!$verificationRecord || $verificationRecord['status'] !== 'revision_requested' || $verificationRecord['reviewer_notes'] !== 'Please add a company website.') {
        throw new RuntimeException('Company revision feedback was not saved.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $pdo->beginTransaction();
    try {
        foreach ([$reviewerId, $employerId] as $userId) {
            if ($userId > 0) {
                $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id = ?')->execute([$userId]);
            }
        }
        if ($companyId > 0) {
            $pdo->prepare('DELETE FROM tbl_companies WHERE id = ?')->execute([$companyId]);
        }
        if ($reviewerId > 0) {
            $pdo->prepare('DELETE FROM tbl_users WHERE id = ?')->execute([$reviewerId]);
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

echo "Company document review, revision, final verification, and audit history verified.\n";
