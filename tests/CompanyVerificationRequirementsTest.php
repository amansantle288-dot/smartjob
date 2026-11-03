<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Company\Controllers\CompanyController;

if (!CompanyController::supports('save_company_requirements')) {
    throw new RuntimeException('Company requirement configuration is not registered.');
}

$pdo = db();
$token = bin2hex(random_bytes(6));
$adminId = 0;
$companyId = 0;
$registrationId = 0;
$permitId = 0;
$secondPermitId = 0;
$previousRequirements = $pdo->query('SELECT company_type, document_type, is_required, applicability, updated_by FROM tbl_company_verification_requirements')->fetchAll();

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')")
        ->execute(['SMARTJOB Requirements Admin', 'company-requirements-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_company_verification_requirements (company_type, document_type, is_required, applicability, updated_by) VALUES (?, ?, 1, \'required\', ?) ON DUPLICATE KEY UPDATE is_required=1, applicability=\'required\', updated_by=VALUES(updated_by)')
        ->execute(['business', 'business_permit', $adminId]);

    $pdo->prepare("INSERT INTO tbl_companies (name, company_type, verification_status) VALUES (?, 'business', 'pending')")
        ->execute(['SMARTJOB Requirements Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_verifications (company_id, status) VALUES (?, 'pending')")
        ->execute([$companyId]);
    $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, review_status) VALUES (?, ?, 'business_registration', 'registration.pdf', ?, 'application/pdf', 100, 'approved')")
        ->execute([$companyId, $adminId, bin2hex(random_bytes(32))]);
    $registrationId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, review_status) VALUES (?, ?, 'bir_registration', 'bir.pdf', ?, 'application/pdf', 100, 'approved'), (?, ?, 'other_business_registration', 'additional-registration.pdf', ?, 'application/pdf', 100, 'approved')")
        ->execute([$companyId, $adminId, bin2hex(random_bytes(32)), $companyId, $adminId, bin2hex(random_bytes(32))]);

    $_SESSION['user_id'] = $adminId;
    $_POST = ['company_id' => $companyId, 'decision' => 'approved', 'remarks' => 'Required-document test.'];
    try {
        (new CompanyController())->handle('review_company');
        throw new RuntimeException('Company approval ignored its configured required business permit.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Submit and obtain approval for all required documents: Business Permit.') {
            throw $exception;
        }
    }

    $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, review_status) VALUES (?, ?, 'business_permit', 'permit.pdf', ?, 'application/pdf', 100, 'approved')")
        ->execute([$companyId, $adminId, bin2hex(random_bytes(32))]);
    $permitId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, review_status) VALUES (?, ?, 'business_permit', 'permit-additional.pdf', ?, 'application/pdf', 100, 'pending')")
        ->execute([$companyId, $adminId, bin2hex(random_bytes(32))]);
    $secondPermitId = (int) $pdo->lastInsertId();
    try {
        (new CompanyController())->handle('review_company');
        throw new RuntimeException('Company approval ignored an unreviewed attachment for a required document type.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Submit and obtain approval for all required documents: Business Permit.') {
            throw $exception;
        }
    }
    $pdo->prepare("UPDATE tbl_company_documents SET review_status='approved' WHERE id=?")->execute([$secondPermitId]);
    $result = (new CompanyController())->handle('review_company');
    if ($result['notice'] !== 'Company review saved.') {
        throw new RuntimeException('Company approval did not succeed after all configured documents were approved.');
    }

    $settings = [];
    foreach (CompanyController::COMPANY_TYPES as $companyType => $_label) {
        foreach (CompanyController::DOCUMENT_TYPES as $documentType) {
            $settings[$companyType][$documentType] = in_array($documentType, CompanyController::BUSINESS_REGISTRATION_TYPES, true)
                ? 'required'
                : 'optional';
        }
    }
    $settings['educational']['authorization'] = 'not_applicable';
    $_POST = ['requirements' => $settings];
    $savedSettings = (new CompanyController())->handle('save_company_requirements');
    if ($savedSettings['notice'] !== 'Company verification requirements updated.') {
        throw new RuntimeException('Company verification requirements could not be saved.');
    }
    $savedRequirementQuery = $pdo->prepare('SELECT applicability FROM tbl_company_verification_requirements WHERE company_type=? AND document_type=?');
    $savedRequirementQuery->execute(['educational', 'authorization']);
    if ($savedRequirementQuery->fetchColumn() !== 'not_applicable') {
        throw new RuntimeException('The selected requirement setting was not persisted.');
    }
    $mandatoryRequirementQuery = $pdo->prepare('SELECT applicability FROM tbl_company_verification_requirements WHERE company_type=? AND document_type=?');
    foreach (CompanyController::COMPANY_TYPES as $companyType => $_label) {
        foreach (CompanyController::BUSINESS_REGISTRATION_TYPES as $documentType) {
            $mandatoryRequirementQuery->execute([$companyType, $documentType]);
            if ($mandatoryRequirementQuery->fetchColumn() !== 'required') {
                throw new RuntimeException('Saving requirements made a globally mandatory business registration optional.');
            }
        }
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $pdo->beginTransaction();
    try {
        if ($adminId) {
            $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id=?')->execute([$adminId]);
            $pdo->prepare('DELETE FROM tbl_notifications WHERE user_id=?')->execute([$adminId]);
        }
        if ($companyId) {
            $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
        }
        $deleteRequirement = $pdo->prepare('DELETE FROM tbl_company_verification_requirements WHERE company_type=? AND document_type=?');
        foreach (CompanyController::COMPANY_TYPES as $companyType => $_label) {
            foreach (CompanyController::DOCUMENT_TYPES as $documentType) {
                $deleteRequirement->execute([$companyType, $documentType]);
            }
        }
        $restoreRequirement = $pdo->prepare('INSERT INTO tbl_company_verification_requirements (company_type, document_type, is_required, applicability, updated_by) VALUES (?, ?, ?, ?, ?)');
        foreach ($previousRequirements as $previousRequirement) {
            $restoreRequirement->execute([
                $previousRequirement['company_type'],
                $previousRequirement['document_type'],
                $previousRequirement['is_required'],
                $previousRequirement['applicability'],
                $previousRequirement['updated_by'],
            ]);
        }
        if ($adminId) {
            $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$adminId]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

echo "Company verification saves all organization settings while preserving mandatory business registrations and approval rules.\n";
