<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Credentials\Controllers;

use RuntimeException;
use Throwable;

final class CredentialController
{
    private const ACTIONS = ['upload_credential', 'delete_credential'];

    public static function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    /**
     * @return array{page: string, notice: string}
     */
    public function handle(string $action): array
    {
        return match ($action) {
            'upload_credential' => $this->upload(),
            'delete_credential' => $this->delete(),
            default => throw new RuntimeException('Unknown credential action.'),
        };
    }

    public function download(): never
    {
        \require_login();
        $credentialId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$credentialId) {
            throw new RuntimeException('Invalid document request.');
        }

        $user = \auth_user();
        $userId = (int) $user['id'];
        $stmt = \db()->prepare(
            "SELECT c.*,
                (c.user_id = ?) AS is_owner,
                EXISTS (
                    SELECT 1 FROM tbl_application_documents ad
                    JOIN tbl_applications a ON a.id = ad.application_id
                    JOIN tbl_internship_opportunities i ON i.id = a.internship_id
                    JOIN tbl_company_members cm ON cm.company_id = i.company_id
                    WHERE ad.credential_id = c.id AND cm.user_id = ? AND cm.status = 'active'
                ) AS is_shared_company_member
                , EXISTS (
                    SELECT 1 FROM tbl_student_trainings t
                    JOIN tbl_student_profiles sp ON sp.user_id=t.user_id
                    JOIN tbl_ojt_coordinators oc ON oc.institution_id=sp.institution_id
                    WHERE t.credential_id=c.id AND t.verification_status='pending'
                      AND oc.user_id=? AND oc.status='active'
                ) AS is_coordinator_training_evidence
             FROM tbl_student_credentials c WHERE c.id = ?"
        );
        $stmt->execute([$userId, $userId, $userId, $credentialId]);
        $credential = $stmt->fetch();
        $ownerAccess = $credential && (bool) $credential['is_owner'];
        $companyAccess = $credential && $user['role'] === 'employer' && (bool) $credential['is_shared_company_member']
            && \require_approved_company($userId) > 0;
        $coordinatorAccess = $credential && $user['role'] === 'coordinator' && (bool) $credential['is_coordinator_training_evidence'];
        if (!$credential || (!$ownerAccess && !$companyAccess && !$coordinatorAccess)) {
            http_response_code(404);
            throw new RuntimeException('Document not found or you are not authorized to access it.');
        }

        $path = \CredentialStorage::path($credential['storage_key']);
        if (!is_file($path)) {
            throw new RuntimeException('The stored document is missing. Please contact support.');
        }

        \audit_log($userId, 'credential.downloaded', 'credential', $credentialId);
        header('Content-Type: ' . $credential['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="document"; filename*=UTF-8\'\'' . rawurlencode($credential['original_name']));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function upload(): array
    {
        $userId = $this->studentId();
        $category = (string) ($_POST['category'] ?? '');
        $allowedCategories = ['resume', 'biodata', 'enrollment', 'endorsement', 'training_certificate', 'seminar_certificate', 'academic_record', 'portfolio', 'internship_requirement', 'other'];
        $description = trim((string) ($_POST['description'] ?? ''));
        $issued = trim((string) ($_POST['issued_at'] ?? ''));
        $expires = trim((string) ($_POST['expires_at'] ?? ''));
        if (!in_array($category, $allowedCategories, true) || mb_strlen($description) > 500 || ($issued !== '' && !\valid_date($issued)) || ($expires !== '' && !\valid_date($expires))) {
            throw new RuntimeException('Select a valid category, description, and issue/expiration date.');
        }

        $stored = \CredentialStorage::store($_FILES['credential'] ?? []);
        try {
            \db()->prepare('INSERT INTO tbl_student_credentials (user_id, category, description, original_name, storage_key, mime_type, file_size, issued_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$userId, $category, $description ?: null, $stored['original_name'], $stored['storage_key'], $stored['mime_type'], $stored['file_size'], $issued ?: null, $expires ?: null]);
        } catch (Throwable $exception) {
            $storedPath = \CredentialStorage::path($stored['storage_key']);
            if (is_file($storedPath)) {
                unlink($storedPath);
            }
            throw $exception;
        }

        \audit_log($userId, 'credential.uploaded', 'credential', (int) \db()->lastInsertId());

        return ['page' => 'credentials', 'notice' => 'Credential uploaded to private storage.'];
    }

    private function delete(): array
    {
        $userId = $this->studentId();
        $stmt = \db()->prepare('SELECT id, storage_key FROM tbl_student_credentials WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($_POST['credential_id'] ?? 0), $userId]);
        $credential = $stmt->fetch();
        if (!$credential) {
            throw new RuntimeException('Credential not found.');
        }

        $shared = \db()->prepare('SELECT COUNT(*) FROM tbl_application_documents WHERE credential_id=?');
        $shared->execute([(int) $credential['id']]);
        if ((int) $shared->fetchColumn() > 0) {
            throw new RuntimeException('This document has been shared with an application and cannot be deleted while that application record is retained.');
        }

        \db()->prepare('DELETE FROM tbl_student_credentials WHERE id = ? AND user_id = ?')
            ->execute([(int) $credential['id'], $userId]);
        $storedPath = \CredentialStorage::path($credential['storage_key']);
        if (is_file($storedPath)) {
            unlink($storedPath);
        }

        return ['page' => 'credentials', 'notice' => 'Credential deleted.'];
    }

    private function studentId(): int
    {
        \require_role('student');

        return (int) \auth_user()['id'];
    }
}
