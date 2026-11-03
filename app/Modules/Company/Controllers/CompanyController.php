<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Company\Controllers;

use PDO;
use RuntimeException;

final class CompanyController
{
    public const COMPANY_TYPES = [
        'business' => 'Business',
        'nonprofit' => 'Non-profit organization',
        'educational' => 'Educational institution',
        'government' => 'Government organization',
        'other' => 'Other organization',
    ];

    public const DOCUMENT_TYPES = [
        'business_registration',
        'business_permit',
        'bir_registration',
        'other_business_registration',
        'representative_id',
        'authorization',
        'representative_information',
        'training_plan',
        'memorandum',
        'internship_program',
        'supervisor_information',
        'additional_certificate',
        'accreditation',
        'supporting_letter',
        'other',
    ];

    public const BUSINESS_REGISTRATION_TYPES = [
        'business_registration',
        'business_permit',
        'bir_registration',
        'other_business_registration',
    ];

    public const DOCUMENT_SECTIONS = [
        'Business registration documents' => [
            'business_registration' => 'DTI / SEC / CDA registration certificate',
            'business_permit' => 'Business permit',
            'bir_registration' => 'BIR registration certificate',
            'other_business_registration' => 'Other business registration document',
        ],
        'Company representative documents' => [
            'representative_id' => 'Authorized representative identification',
            'authorization' => 'Authorization letter',
            'representative_information' => 'Company representative information form',
        ],
        'OJT partnership requirements' => [
            'memorandum' => 'Memorandum of Agreement (MOA)',
            'training_plan' => 'Company training plan',
            'internship_program' => 'Internship program description',
            'supervisor_information' => 'Supervisor information',
            'other' => 'Other institutional requirement',
        ],
        'Additional supporting documents' => [
            'additional_certificate' => 'Additional certificate',
            'accreditation' => 'Accreditation document',
            'supporting_letter' => 'Supporting letter',
        ],
    ];

    public const REQUIREMENT_APPLICABILITY = [
        'required' => 'Mandatory',
        'optional' => 'Optional',
        'not_applicable' => 'Not applicable',
    ];

    public static function supports(string $action): bool
    {
        return in_array($action, ['review_company', 'review_company_document', 'upload_company_document', 'upload_company_documents', 'save_company_profile', 'save_company_requirements'], true);
    }

    /**
     * @return array{page: string, notice: string}
     */
    public function handle(string $action): array
    {
        if ($action === 'save_company_profile') {
            return $this->saveProfile();
        }
        if ($action === 'save_company_requirements') {
            return $this->saveRequirements();
        }
        if ($action === 'upload_company_document') {
            return $this->uploadDocument();
        }
        if ($action === 'upload_company_documents') {
            return $this->uploadDocuments();
        }
        if ($action === 'review_company_document') {
            return $this->reviewDocument();
        }
        if ($action !== 'review_company') {
            throw new RuntimeException('Unknown company action.');
        }

        \require_role('admin');
        $reviewerId = (int) \auth_user()['id'];
        $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        if (!$companyId || !in_array($decision, ['approved', 'rejected', 'revision_requested'], true) || mb_strlen($remarks) > 1000 || ($decision === 'revision_requested' && $remarks === '')) {
            throw new RuntimeException('Invalid company review details.');
        }

        \db_transaction(function (PDO $pdo) use ($decision, $remarks, $companyId, $reviewerId): void {
            $companyLock = $pdo->prepare('SELECT company_type FROM tbl_companies WHERE id=? FOR UPDATE');
            $companyLock->execute([$companyId]);
            $companyType = $companyLock->fetchColumn();
            if (!$companyType) {
                throw new RuntimeException('Company not found for review.');
            }
            if ($decision === 'approved') {
                $requiredDocuments = $pdo->prepare("SELECT document_type FROM tbl_company_verification_requirements WHERE company_type=? AND applicability='required'");
                $requiredDocuments->execute([$companyType]);
                $requiredTypes = array_values(array_unique([
                    ...self::BUSINESS_REGISTRATION_TYPES,
                    ...$requiredDocuments->fetchAll(PDO::FETCH_COLUMN),
                ]));
                $missingTypes = [];
                foreach ($requiredTypes as $documentType) {
                    $currentDocument = $pdo->prepare('SELECT COUNT(*) AS submitted_count, SUM(d.review_status=\'approved\') AS approved_count FROM tbl_company_documents d WHERE d.company_id=? AND d.document_type=? AND NOT EXISTS (SELECT 1 FROM tbl_company_documents newer WHERE newer.previous_document_id=d.id)');
                    $currentDocument->execute([$companyId, $documentType]);
                    $documentStatus = $currentDocument->fetch();
                    if (!$documentStatus || (int) $documentStatus['submitted_count'] === 0
                        || (int) $documentStatus['submitted_count'] !== (int) $documentStatus['approved_count']) {
                        $missingTypes[] = ucwords(str_replace('_', ' ', (string) $documentType));
                    }
                }
                if ($missingTypes) {
                    throw new RuntimeException('Submit and obtain approval for all required documents: ' . implode(', ', $missingTypes) . '.');
                }
                $documents = $pdo->prepare(
                    'SELECT COUNT(*) AS submitted_count, SUM(d.review_status=\'approved\') AS approved_count
                     FROM tbl_company_documents d
                     WHERE d.company_id=?
                       AND NOT EXISTS (SELECT 1 FROM tbl_company_documents newer WHERE newer.previous_document_id=d.id)'
                );
                $documents->execute([$companyId]);
                $documentStatus = $documents->fetch();
                if (!$documentStatus || (int) $documentStatus['submitted_count'] === 0
                    || (int) $documentStatus['submitted_count'] !== (int) $documentStatus['approved_count']) {
                    throw new RuntimeException('Approve all current company verification documents before completing final company verification.');
                }
            }

            $companyStatus = $decision === 'revision_requested' ? 'pending' : $decision;
            $pdo->prepare('UPDATE tbl_companies SET verification_status = ?, verified_at = ?, verified_by = ? WHERE id = ?')
                ->execute([$companyStatus, $decision === 'approved' ? date('Y-m-d H:i:s') : null, $decision === 'approved' ? $reviewerId : null, $companyId]);

            $verificationRow = $pdo->prepare('SELECT id FROM tbl_company_verifications WHERE company_id = ?');
            $verificationRow->execute([$companyId]);
            if ($verificationRow->fetchColumn()) {
                $pdo->prepare('UPDATE tbl_company_verifications SET status = ?, reviewer_id = ?, reviewer_notes = ?, reviewed_at = NOW() WHERE company_id = ?')
                    ->execute([$decision, $reviewerId, $remarks ?: null, $companyId]);
            } else {
                $pdo->prepare('INSERT INTO tbl_company_verifications (company_id, status, reviewer_id, reviewer_notes, reviewed_at) VALUES (?, ?, ?, ?, NOW())')
                    ->execute([$companyId, $decision, $reviewerId, $remarks ?: null]);
            }

            $pdo->prepare('INSERT INTO tbl_verification_logs (company_id, reviewer_id, decision, notes) VALUES (?, ?, ?, ?)')
                ->execute([$companyId, $reviewerId, $decision, $remarks ?: null]);
            \audit_log($reviewerId, 'company.' . $decision, 'company', $companyId, 'success', 'Company verification decision recorded: ' . str_replace('_', ' ', $decision) . '.');
            $notificationTitle = $decision === 'approved' ? 'Company verified'
                : ($decision === 'revision_requested' ? 'Company profile returned for revision' : 'Company verification decision');
            $notificationMessage = $decision === 'revision_requested'
                ? 'Your company profile needs updates: ' . $remarks
                : ($decision === 'approved' ? 'Your company has completed administrator verification.' : 'An administrator has updated your company verification status.');
            $pdo->prepare('INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT user_id, ?, ?, \'company\', ? FROM tbl_company_members WHERE company_id=? AND status=\'active\'')
                ->execute([$notificationTitle, $notificationMessage, $companyId, $companyId]);
        });

        $notice = $decision === 'revision_requested'
            ? 'Revision request sent to the company.'
            : 'Company review saved.';
        return ['page' => 'companies', 'notice' => $notice];
    }

    public function streamDocument(): never
    {
        \require_login();
        $documentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$documentId) {
            throw new RuntimeException('Invalid verification document request.');
        }

        $query = \db()->prepare('SELECT d.*, c.name AS company_name FROM tbl_company_documents d JOIN tbl_companies c ON c.id=d.company_id WHERE d.id=?');
        $query->execute([$documentId]);
        $document = $query->fetch();
        $user = \auth_user();
        $authorized = $document && $user['role'] === 'admin';
        if ($document && $user['role'] === 'employer') {
            $authorized = \current_company_id((int) $user['id']) === (int) $document['company_id'];
        }
        if (!$authorized) {
            http_response_code(404);
            throw new RuntimeException('Verification document not found or you are not authorized to access it.');
        }

        $path = \CredentialStorage::path((string) $document['storage_key']);
        if (!is_file($path)) {
            throw new RuntimeException('The stored verification document is missing. Please contact support.');
        }
        if (!in_array($document['mime_type'], ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('This verification document has an unsupported stored format.');
        }
        \audit_log((int) $user['id'], 'company_document.viewed', 'company_document', $documentId);
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header($document['mime_type'] === 'application/pdf' || str_starts_with($document['mime_type'], 'image/')
            ? 'Content-Disposition: inline; filename="verification-document"'
            : 'Content-Disposition: attachment; filename="verification-document.docx"');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public function streamLogo(): never
    {
        $companyId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $user = \auth_user();
        $userId = $user ? (int) $user['id'] : 0;
        $query = \db()->prepare("SELECT c.logo_key FROM tbl_companies c WHERE c.id=? AND (c.verification_status='approved' OR EXISTS (SELECT 1 FROM tbl_company_members cm WHERE cm.company_id=c.id AND cm.user_id=? AND cm.status='active'))");
        $query->execute([$companyId ?: 0, $userId]);
        $key = $query->fetchColumn();
        $path = is_string($key) && $key !== '' ? \ProfilePhotoStorage::path($key) : '';
        $mime = $path !== '' && is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/png'], true)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function saveProfile(): array
    {
        \require_role('employer');
        $userId = (int) \auth_user()['id'];
        $companyId = \current_company_id($userId);
        $name = trim((string) ($_POST['company_name'] ?? ''));
        $industry = trim((string) ($_POST['industry'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $mission = trim((string) ($_POST['mission_statement'] ?? ''));
        $vision = trim((string) ($_POST['vision_statement'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $website = trim((string) ($_POST['website'] ?? ''));
        $companyType = (string) ($_POST['company_type'] ?? 'other');
        $websiteScheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));
        if ($name === '' || mb_strlen($name) > 180 || mb_strlen($industry) > 120 || mb_strlen($description) > 10000 || mb_strlen($mission) > 5000 || mb_strlen($vision) > 5000 || mb_strlen($address) > 255 || mb_strlen($website) > 255 || !isset(self::COMPANY_TYPES[$companyType]) || ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || $websiteScheme !== 'https'))) {
            throw new RuntimeException('Check the company fields and use a valid HTTPS website address.');
        }

        $existing = \db()->prepare('SELECT logo_key, company_type, verification_status FROM tbl_companies WHERE id=?');
        $existing->execute([$companyId]);
        $existingCompany = $existing->fetch();
        if (!$existingCompany) {
            throw new RuntimeException('Your company account could not be found.');
        }
        if ($existingCompany['verification_status'] === 'approved' && $existingCompany['company_type'] !== $companyType) {
            throw new RuntimeException('Company type cannot be changed after verification. Contact an administrator if it must be corrected.');
        }
        $oldLogo = $existingCompany['logo_key'];
        $photo = null;
        if (isset($_FILES['company_logo']) && ($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $photo = \ProfilePhotoStorage::store($_FILES['company_logo']);
        }
        $resubmitted = false;
        try {
            \db_transaction(function (PDO $pdo) use ($name, $companyType, $industry, $description, $mission, $vision, $address, $website, $photo, $companyId, $userId, &$resubmitted): void {
                $lock = $pdo->prepare('SELECT company_type, verification_status FROM tbl_companies WHERE id=? FOR UPDATE');
                $lock->execute([$companyId]);
                $currentCompany = $lock->fetch();
                if (!$currentCompany) {
                    throw new RuntimeException('Your company account could not be found.');
                }
                if ($currentCompany['verification_status'] === 'approved' && $currentCompany['company_type'] !== $companyType) {
                    throw new RuntimeException('Company type cannot be changed after verification. Contact an administrator if it must be corrected.');
                }
                $update = $pdo->prepare('UPDATE tbl_companies SET name=?, company_type=?, industry=?, description=?, mission_statement=?, vision_statement=?, address=?, website=?, logo_key=COALESCE(?, logo_key) WHERE id=?');
                $update->execute([$name, $companyType, $industry ?: null, $description ?: null, $mission ?: null, $vision ?: null, $address ?: null, $website ?: null, $photo['key'] ?? null, $companyId]);
                $revision = $pdo->prepare("UPDATE tbl_company_verifications SET status='pending', reviewer_notes=NULL, reviewed_at=NULL WHERE company_id=? AND status='revision_requested'");
                $revision->execute([$companyId]);
                if ($revision->rowCount() > 0) {
                    $pdo->prepare("UPDATE tbl_companies SET verification_status='pending', verified_at=NULL, verified_by=NULL WHERE id=?")
                        ->execute([$companyId]);
                    $resubmitted = true;
                }

                \audit_log($userId, 'company.profile_updated', 'company', $companyId);
            });
        } catch (\Throwable $exception) {
            if ($photo) {
                \ProfilePhotoStorage::remove($photo['key']);
            }
            throw $exception;
        }
        if ($photo && is_string($oldLogo) && $oldLogo !== '' && $oldLogo !== $photo['key']) {
            \ProfilePhotoStorage::remove($oldLogo);
        }
        return [
            'page' => 'company_profile',
            'notice' => $resubmitted
                ? 'Company profile saved and resubmitted for verification.'
                : 'Company profile saved.',
        ];
    }

    private function saveRequirements(): array
    {
        \require_role('admin');
        $adminId = (int) \auth_user()['id'];
        $requirements = $_POST['requirements'] ?? [];
        if (!is_array($requirements)) {
            throw new RuntimeException('Select valid company verification requirements.');
        }
        foreach (array_keys($requirements) as $companyType) {
            if (!isset(self::COMPANY_TYPES[$companyType])) {
                throw new RuntimeException('Select valid company verification requirements.');
            }
        }

        \db_transaction(function (PDO $pdo) use ($adminId, $requirements): void {
            $upsert = $pdo->prepare(
                'INSERT INTO tbl_company_verification_requirements (company_type, document_type, is_required, applicability, updated_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_required=VALUES(is_required), applicability=VALUES(applicability), updated_by=VALUES(updated_by)'
            );
            foreach (self::COMPANY_TYPES as $companyType => $_label) {
                $selected = $requirements[$companyType] ?? [];
                if (!is_array($selected)) {
                    throw new RuntimeException('Select valid company verification requirements.');
                }
                foreach ($selected as $documentType => $value) {
                    if (!in_array($documentType, self::DOCUMENT_TYPES, true) || !is_string($value) || !isset(self::REQUIREMENT_APPLICABILITY[$value])) {
                        throw new RuntimeException('Select valid company verification requirements.');
                    }
                }
                foreach (self::DOCUMENT_TYPES as $documentType) {
                    $applicability = in_array($documentType, self::BUSINESS_REGISTRATION_TYPES, true)
                        ? 'required'
                        : ($selected[$documentType] ?? 'optional');
                    $upsert->execute([$companyType, $documentType, $applicability === 'required' ? 1 : 0, $applicability, $adminId]);
                }
            }
            \audit_log($adminId, 'company_requirements.updated', 'company_verification_requirement');
        });

        return ['page' => 'companies', 'notice' => 'Company verification requirements updated.'];
    }

    private function uploadDocument(): array
    {
        \require_role('employer');
        $type = (string) ($_POST['document_type'] ?? '');
        $previousIdInput = trim((string) ($_POST['previous_document_id'] ?? ''));
        $previousId = $previousIdInput === '' ? null : filter_var($previousIdInput, FILTER_VALIDATE_INT);
        if (!in_array($type, self::DOCUMENT_TYPES, true) || ($previousIdInput !== '' && (!$previousId || $previousId < 1))) {
            throw new RuntimeException('Choose a valid company verification document type.');
        }

        $validated = \CompanyDocumentStorage::validate($_FILES['company_document'] ?? []);
        return $this->persistDocumentBatch([[
            'document_type' => $type,
            'previous_document_id' => $previousId ? (int) $previousId : null,
            'validated_file' => $validated,
        ]]);
    }

    private function uploadDocuments(): array
    {
        \require_role('employer');
        $files = [];
        foreach ([
            ['field' => $_FILES['documents'] ?? [], 'kind' => 'requirement'],
            ['field' => $_FILES['replacements'] ?? [], 'kind' => 'replacement'],
        ] as $group) {
            foreach ($this->flattenUploadTree($group['field']) as $entry) {
                if ((int) $entry['file']['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($group['kind'] === 'requirement') {
                    $documentType = (string) ($entry['path'][0] ?? '');
                    $previousDocumentId = null;
                } else {
                    $documentType = '';
                    $previousDocumentId = filter_var($entry['path'][0] ?? null, FILTER_VALIDATE_INT);
                    if (!$previousDocumentId || $previousDocumentId < 1) {
                        throw new RuntimeException('A returned document selection is invalid.');
                    }
                    $documentQuery = \db()->prepare('SELECT document_type FROM tbl_company_documents WHERE id=? AND company_id=? AND review_status=\'revision_requested\' AND NOT EXISTS (SELECT 1 FROM tbl_company_documents newer WHERE newer.previous_document_id=tbl_company_documents.id)');
                    $documentQuery->execute([(int) $previousDocumentId, \current_company_id((int) \auth_user()['id'])]);
                    $documentType = (string) $documentQuery->fetchColumn();
                }
                if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
                    throw new RuntimeException('Choose a valid company verification document requirement.');
                }
                $files[] = [
                    'document_type' => $documentType,
                    'previous_document_id' => $previousDocumentId ? (int) $previousDocumentId : null,
                    'validated_file' => \CompanyDocumentStorage::validate($entry['file']),
                ];
            }
        }

        if (!$files) {
            throw new RuntimeException('Select at least one verification document to submit.');
        }
        return $this->persistDocumentBatch($files);
    }

    private function persistDocumentBatch(array $files): array
    {
        $totalBytes = array_sum(array_column(array_column($files, 'validated_file'), 'file_size'));
        if ($totalBytes > \CompanyDocumentStorage::maxBatchBytes()) {
            throw new RuntimeException('The selected documents exceed the total batch limit of ' . self::formatDocumentBytes(\CompanyDocumentStorage::maxBatchBytes()) . '.');
        }
        $userId = (int) \auth_user()['id'];
        $companyId = \current_company_id($userId);
        $storedFiles = [];
        try {
            foreach ($files as $index => $file) {
                $files[$index]['stored_file'] = \CompanyDocumentStorage::store($file['validated_file']);
                $storedFiles[] = $files[$index]['stored_file']['storage_key'];
            }
            $batchKey = bin2hex(random_bytes(16));
            $documentCount = count($files);
            $batchId = 0;
            \db_transaction(function (PDO $pdo) use ($files, $userId, $companyId, $batchKey, $documentCount, $totalBytes, &$batchId): void {
                $companyLock = $pdo->prepare('SELECT id FROM tbl_companies WHERE id=? FOR UPDATE');
                $companyLock->execute([$companyId]);
                if (!$companyLock->fetchColumn()) {
                    throw new RuntimeException('Your company account could not be found.');
                }
                $previousIds = [];
                $previousQuery = $pdo->prepare('SELECT id, document_type, review_status FROM tbl_company_documents WHERE id=? AND company_id=? FOR UPDATE');
                $replacementExists = $pdo->prepare('SELECT 1 FROM tbl_company_documents WHERE previous_document_id=? LIMIT 1');
                foreach ($files as $file) {
                    $previousId = $file['previous_document_id'];
                    if ($previousId === null) {
                        continue;
                    }
                    if (isset($previousIds[$previousId])) {
                        throw new RuntimeException('Each returned document can only have one replacement in a submission.');
                    }
                    $previousIds[$previousId] = true;
                    $previousQuery->execute([$previousId, $companyId]);
                    $previousDocument = $previousQuery->fetch();
                    $replacementExists->execute([$previousId]);
                    if (!$previousDocument || $previousDocument['document_type'] !== $file['document_type']
                        || $previousDocument['review_status'] !== 'revision_requested' || $replacementExists->fetchColumn()) {
                        throw new RuntimeException('A returned document is no longer available for replacement.');
                    }
                }

                $pdo->prepare('INSERT INTO tbl_company_document_batches (batch_key, company_id, uploaded_by, document_count, total_bytes) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$batchKey, $companyId, $userId, $documentCount, $totalBytes]);
                $batchId = (int) $pdo->lastInsertId();
                $insertDocument = $pdo->prepare('INSERT INTO tbl_company_documents (company_id, uploaded_by, document_type, original_name, storage_key, mime_type, file_size, review_status, previous_document_id, batch_id, version_number) VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?)');
                $history = $pdo->prepare("INSERT INTO tbl_company_document_review_history (document_id, action) VALUES (?, 'uploaded')");
                foreach ($files as $file) {
                    $version = 1;
                    if ($file['previous_document_id'] !== null) {
                        $versionQuery = $pdo->prepare('SELECT version_number FROM tbl_company_documents WHERE id=?');
                        $versionQuery->execute([$file['previous_document_id']]);
                        $version = (int) $versionQuery->fetchColumn() + 1;
                    }
                    $stored = $file['stored_file'];
                    $insertDocument->execute([$companyId, $userId, $file['document_type'], $stored['original_name'], $stored['storage_key'], $stored['mime_type'], $stored['file_size'], $file['previous_document_id'], $batchId, $version]);
                    $documentId = (int) $pdo->lastInsertId();
                    $history->execute([$documentId]);
                    \audit_log($userId, 'company_document.uploaded', 'company_document', $documentId, 'success', 'Company submitted a verification document.');
                }
                $summary = $documentCount . ' verification document' . ($documentCount === 1 ? '' : 's') . ' submitted by ' . \auth_user()['full_name'] . ' for administrator review.';
                $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT id, 'Company documents submitted', ?, 'company', ? FROM tbl_users WHERE role='admin' AND status='active'")
                    ->execute([$summary, $companyId]);
                $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT user_id, 'Verification documents received', ?, 'company', ? FROM tbl_company_members WHERE company_id=? AND status='active'")
                    ->execute(['Your submission of ' . $documentCount . ' verification document' . ($documentCount === 1 ? '' : 's') . ' was received for administrator review.', $companyId, $companyId]);
                if ($previousIds) {
                    $pdo->prepare("UPDATE tbl_company_verifications SET status='pending', reviewer_notes=NULL, reviewed_at=NULL WHERE company_id=? AND status='revision_requested'")
                        ->execute([$companyId]);
                }
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $storageKey) {
                \CompanyDocumentStorage::remove($storageKey);
            }
            throw $exception;
        }

        return [
            'page' => 'company_verification',
            'notice' => $documentCount . ' verification document' . ($documentCount === 1 ? '' : 's') . ' submitted together for review.',
            'json' => ['documents_uploaded' => $documentCount, 'batch_id' => $batchId, 'status' => 'submitted'],
        ];
    }

    private function flattenUploadTree(array $field): array
    {
        if (!isset($field['name'])) {
            return [];
        }
        $entries = [];
        $walk = function (array $node, array $path) use (&$walk, &$entries): void {
            if (is_array($node['name'] ?? null)) {
                foreach (array_keys($node['name']) as $key) {
                    $child = [];
                    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $property) {
                        $child[$property] = $node[$property][$key] ?? null;
                    }
                    $walk($child, [...$path, $key]);
                }
                return;
            }
            $entries[] = [
                'path' => $path,
                'file' => [
                    'name' => $node['name'] ?? '',
                    'type' => $node['type'] ?? '',
                    'tmp_name' => $node['tmp_name'] ?? '',
                    'error' => $node['error'] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $node['size'] ?? 0,
                ],
            ];
        };
        $walk($field, []);
        return $entries;
    }

    private static function formatDocumentBytes(int $bytes): string
    {
        return $bytes >= 1_048_576 ? rtrim(rtrim(number_format($bytes / 1_048_576, 1), '0'), '.') . ' MB' : (int) ceil($bytes / 1024) . ' KB';
    }

    private function reviewDocument(): array
    {
        \require_role('admin');
        $reviewerId = (int) \auth_user()['id'];
        $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (!$documentId || !in_array($decision, ['under_review', 'approved', 'revision_requested'], true)
            || mb_strlen($notes) > 1000 || ($decision === 'revision_requested' && $notes === '')) {
            throw new RuntimeException('Choose a valid document review action; a clear reason is required when returning a document.');
        }

        \db_transaction(function (PDO $pdo) use ($reviewerId, $documentId, $decision, $notes): void {
            $companyIdQuery = $pdo->prepare('SELECT company_id FROM tbl_company_documents WHERE id=?');
            $companyIdQuery->execute([$documentId]);
            $companyId = $companyIdQuery->fetchColumn();
            if (!$companyId) {
                throw new RuntimeException('Verification document not found.');
            }
            $companyLock = $pdo->prepare('SELECT id FROM tbl_companies WHERE id=? FOR UPDATE');
            $companyLock->execute([(int) $companyId]);
            $query = $pdo->prepare('SELECT id, company_id, document_type, review_status FROM tbl_company_documents WHERE id=? FOR UPDATE');
            $query->execute([$documentId]);
            $document = $query->fetch();
            if (!$document || !in_array($document['review_status'], ['pending', 'under_review'], true)) {
                throw new RuntimeException('This document is no longer awaiting review.');
            }

            $pdo->prepare('UPDATE tbl_company_documents SET review_status=?, reviewer_id=?, reviewer_notes=?, reviewed_at=IF(?=\'under_review\', NULL, NOW()) WHERE id=?')
                ->execute([$decision, $reviewerId, $notes ?: null, $decision, $documentId]);
            $pdo->prepare('INSERT INTO tbl_company_document_review_history (document_id, reviewer_id, action, notes) VALUES (?, ?, ?, ?)')
                ->execute([$documentId, $reviewerId, $decision, $notes ?: null]);
            if ($decision === 'revision_requested') {
                $pdo->prepare("UPDATE tbl_companies SET verification_status='pending', verified_at=NULL, verified_by=NULL WHERE id=?")
                    ->execute([(int) $document['company_id']]);
                $verification = $pdo->prepare('INSERT INTO tbl_company_verifications (company_id, status, reviewer_id, reviewer_notes, reviewed_at) VALUES (?, \'revision_requested\', ?, ?, NOW()) ON DUPLICATE KEY UPDATE status=\'revision_requested\', reviewer_id=VALUES(reviewer_id), reviewer_notes=VALUES(reviewer_notes), reviewed_at=NOW()');
                $verification->execute([(int) $document['company_id'], $reviewerId, $notes]);
            }

            $title = $decision === 'approved' ? 'Verification document approved' : ($decision === 'revision_requested' ? 'Verification document returned' : 'Verification document under review');
            $message = $decision === 'revision_requested'
                ? 'Your ' . str_replace('_', ' ', $document['document_type']) . ' was returned for revision: ' . $notes
                : 'Your ' . str_replace('_', ' ', $document['document_type']) . ' is ' . str_replace('_', ' ', $decision) . '.';
            $pdo->prepare('INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT user_id, ?, ?, \'company\', ? FROM tbl_company_members WHERE company_id=? AND status=\'active\'')
                ->execute([$title, $message, (int) $document['company_id'], (int) $document['company_id']]);
            if ($decision === 'revision_requested') {
                $pdo->prepare('INSERT INTO tbl_verification_logs (company_id, reviewer_id, decision, notes) VALUES (?, ?, \'revision_requested\', ?)')
                    ->execute([(int) $document['company_id'], $reviewerId, $notes]);
            }
            \audit_log($reviewerId, 'company_document.' . $decision, 'company_document', $documentId, 'success', 'Company verification document review recorded: ' . str_replace('_', ' ', $decision) . '.');
        });

        return ['page' => 'companies', 'notice' => 'Company document review saved.'];
    }
}
