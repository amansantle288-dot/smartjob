<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Company\Controllers\CompanyController;

if (!CompanyController::supports('upload_company_documents')) {
    throw new RuntimeException('Bulk company document uploads are not registered.');
}

$pdo = db();
$pdo->beginTransaction();
$employerId = 0;
$adminId = 0;
$companyId = 0;

try {
    $token = bin2hex(random_bytes(6));
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')")
        ->execute(['SMARTJOB Bulk Document Test', 'company-batch-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $employerId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')")
        ->execute(['SMARTJOB Bulk Review Test', 'company-batch-admin-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_companies (name, company_type, verification_status) VALUES (?, 'business', 'pending')")
        ->execute(['SMARTJOB Bulk Test Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")
        ->execute([$companyId, $employerId]);
    $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, review_status, reviewer_notes) VALUES (?, ?, 'business_registration', 'returned.pdf', ?, 'application/pdf', 100, 'revision_requested', 'Upload a readable copy.')")
        ->execute([$companyId, $employerId, bin2hex(random_bytes(32))]);
    $returnedDocumentId = (int) $pdo->lastInsertId();
    $_SESSION['user_id'] = $employerId;

    $field = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
    foreach ([
        'business_registration' => ['registration-one.pdf', 'registration-two.pdf'],
        'training_plan' => ['training-plan.docx'],
    ] as $type => $names) {
        foreach ($names as $index => $name) {
            $field['name'][$type][$index] = $name;
            $field['type'][$type][$index] = 'application/octet-stream';
            $field['tmp_name'][$type][$index] = '';
            $field['error'][$type][$index] = UPLOAD_ERR_NO_FILE;
            $field['size'][$type][$index] = 0;
        }
    }
    $normalizer = new ReflectionMethod(CompanyController::class, 'flattenUploadTree');
    $normalizer->setAccessible(true);
    $entries = $normalizer->invoke(new CompanyController(), $field);
    if (count($entries) !== 3 || $entries[0]['path'][0] !== 'business_registration' || $entries[2]['path'][0] !== 'training_plan') {
        throw new RuntimeException('Nested multi-requirement file selections were not normalized correctly.');
    }

    $_FILES = [];
    $_POST = [];
    try {
        (new CompanyController())->handle('upload_company_documents');
        throw new RuntimeException('A bulk submission with no selected files was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Select at least one verification document to submit.') {
            throw $exception;
        }
    }
    $documentCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_company_documents WHERE company_id=?');
    $documentCount->execute([$companyId]);
    $batchCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_company_document_batches WHERE company_id=?');
    $batchCount->execute([$companyId]);
    if ((int) $documentCount->fetchColumn() !== 1 || (int) $batchCount->fetchColumn() !== 0) {
        throw new RuntimeException('An empty or invalid batch left partial document or batch records.');
    }

    ob_start();
    render_page('company_verification', null, []);
    $html = (string) ob_get_clean();
    foreach (['Business registration documents', 'Company representative documents', 'OJT partnership requirements', 'Additional supporting documents', 'upload_company_documents', 'data-replacement-id="' . $returnedDocumentId . '"', 'Upload a readable copy.', 'Mandatory', "companyUploadForm.getAttribute('action')"] as $expected) {
        if (!str_contains($html, $expected)) {
            throw new RuntimeException('The company verification page is missing bulk upload or revision content: ' . $expected);
        }
    }

    $pdo->prepare("INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, review_status) VALUES (?, ?, 'business_permit', 'pending-permit.pdf', ?, 'application/pdf', 100, 'pending')")
        ->execute([$companyId, $employerId, bin2hex(random_bytes(32))]);
    $_SESSION['user_id'] = $adminId;
    ob_start();
    render_page('companies', null, []);
    $adminHtml = (string) ob_get_clean();
    foreach ([
        'DOCUMENT-BY-DOCUMENT REVIEW',
        'Company Verification Requirements',
        'role="tablist"',
        'aria-controls="policy-panel-business"',
        'aria-controls="policy-panel-nonprofit"',
        'aria-controls="policy-panel-educational"',
        'aria-controls="policy-panel-government"',
        'aria-controls="policy-panel-other"',
        'data-policy-search',
        'data-policy-filter',
        'data-policy-reset',
        'data-policy-save',
        'requirements[business][representative_id]',
        'value="required"',
        'Required for all company types',
        'Business registration documents',
        'Batch / version',
        'Approve document',
        'Return for revision',
    ] as $expected) {
        if (!str_contains($adminHtml, $expected)) {
            throw new RuntimeException('The administrator company page is missing verification policy or document review controls: ' . $expected);
        }
    }
} finally {
    unset($_SESSION['user_id']);
    $_FILES = [];
    $_POST = [];
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "Bulk document selection normalization, empty-batch atomicity, and company upload-page rendering verified.\n";
