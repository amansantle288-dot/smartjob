<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Applications\Controllers;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class ApplicationController
{
    private const ACTIONS = ['apply', 'update_biodata_sharing', 'review_qualifications', 'update_application', 'withdraw_application'];

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
            'apply' => $this->apply(),
            'update_biodata_sharing' => $this->updateBiodataSharing(),
            'review_qualifications' => $this->reviewQualifications(),
            'update_application' => $this->updateApplication(),
            'withdraw_application' => $this->withdrawApplication(),
            default => throw new RuntimeException('Unknown application action.'),
        };
    }

    public function showSharedBiodata(): never
    {
        $applicationId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $application = \db()->prepare("SELECT a.id FROM tbl_application_biodata ab JOIN tbl_applications a ON a.id=ab.application_id JOIN tbl_internship_opportunities i ON i.id=a.internship_id WHERE ab.application_id=? AND i.company_id=?");
        $application->execute([$applicationId ?: 0, \require_approved_company((int) \auth_user()['id'])]);
        if (!$application->fetchColumn()) {
            http_response_code(404);
            throw new RuntimeException('This student has not shared biodata with your company for that application.');
        }

        $student = \db()->prepare('SELECT student_id FROM tbl_application_biodata WHERE application_id=?');
        $student->execute([$applicationId]);
        $applicationBiodata = \load_biodata((int) $student->fetchColumn());
        \render_page('applicant_biodata', null, [], ['applicationBiodata' => $applicationBiodata]);
        exit;
    }

    public function downloadSnapshot(): never
    {
        \require_login();
        $snapshotId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$snapshotId) {
            throw new RuntimeException('Invalid application document request.');
        }

        $user = \auth_user();
        $userId = (int) $user['id'];
        $query = \db()->prepare(
            'SELECT s.*, a.student_id, i.company_id,
                EXISTS (SELECT 1 FROM tbl_company_members cm
                        WHERE cm.company_id=i.company_id AND cm.user_id=? AND cm.status=\'active\') AS is_company_member
             FROM tbl_application_document_snapshots s
             JOIN tbl_applications a ON a.id=s.application_id
             JOIN tbl_internship_opportunities i ON i.id=a.internship_id
             WHERE s.id=?'
        );
        $query->execute([$userId, $snapshotId]);
        $snapshot = $query->fetch();
        $isOwner = $snapshot && (int) $snapshot['student_id'] === $userId;
        $isCompanyMember = $snapshot && $user['role'] === 'employer' && (bool) $snapshot['is_company_member'];
        if ($isCompanyMember) {
            $companyStatus = \db()->prepare('SELECT verification_status FROM tbl_companies WHERE id=?');
            $companyStatus->execute([(int) $snapshot['company_id']]);
            if ($companyStatus->fetchColumn() !== 'approved') {
                throw new RuntimeException('Your company must be approved to access application documents.');
            }
        }
        if (!$snapshot || (!$isOwner && !$isCompanyMember)) {
            http_response_code(404);
            throw new RuntimeException('Application document not found or you are not authorized to access it.');
        }

        $path = \CredentialStorage::path($snapshot['storage_key']);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The application document snapshot is missing. Please contact support.');
        }
        $actualHash = hash_file('sha256', $path);
        if (!is_string($actualHash) || !hash_equals((string) $snapshot['sha256'], $actualHash)) {
            throw new RuntimeException('The application document snapshot failed its integrity check. Please contact support.');
        }

        \audit_log($userId, 'application_document.downloaded', 'application_document_snapshot', (int) $snapshotId);
        header('Content-Type: ' . $snapshot['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="document"; filename*=UTF-8\'\'' . rawurlencode($snapshot['original_name']));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function apply(): array
    {
        \require_role('student');
        $studentId = (int) \auth_user()['id'];
        $internshipId = filter_var($_POST['internship_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$internshipId) {
            throw new RuntimeException('Invalid internship.');
        }
        $coverLetter = trim((string) ($_POST['cover_letter'] ?? ''));
        if (mb_strlen($coverLetter) > 5000) {
            throw new RuntimeException('Keep your application letter within 5,000 characters.');
        }

        $credentialIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['credential_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        if (count($credentialIds) > 10) {
            throw new RuntimeException('Choose no more than 10 supporting documents.');
        }
        $shareBiodata = (string) ($_POST['share_biodata'] ?? '') === '1';

        $existingApplication = \db()->prepare('SELECT id FROM tbl_applications WHERE internship_id=? AND student_id=? LIMIT 1');
        $existingApplication->execute([$internshipId, $studentId]);
        if ($existingApplication->fetchColumn()) {
            return ['page' => 'applications', 'notice' => 'You have already applied for this internship. View it in My Applications.'];
        }

        $createdSnapshotKeys = [];
        try {
            \db_transaction(function (PDO $pdo) use ($internshipId, $credentialIds, $shareBiodata, $coverLetter, $studentId, &$createdSnapshotKeys): void {
            $internship = $pdo->prepare("SELECT i.id, i.title, i.company_id FROM tbl_internship_opportunities i JOIN tbl_companies c ON c.id=i.company_id WHERE i.id = ? AND i.status = 'published' AND i.application_deadline >= ? AND c.verification_status='approved' FOR UPDATE");
            $internship->execute([$internshipId, \HiringCountdown::today()]);
            $opportunity = $internship->fetch();
            if (!$opportunity) {
                throw new RuntimeException('This opportunity is no longer accepting applications.');
            }

            $pdo->prepare("INSERT INTO tbl_applications (internship_id, student_id, cover_letter, status) VALUES (?, ?, ?, 'submitted')")
                ->execute([$internshipId, $studentId, $coverLetter ?: null]);
            $applicationId = (int) $pdo->lastInsertId();
            if ($shareBiodata) {
                $pdo->prepare('INSERT INTO tbl_application_biodata (application_id, student_id) VALUES (?, ?)')
                    ->execute([$applicationId, $studentId]);
            }
            $pdo->prepare('INSERT INTO tbl_application_status_history (application_id, changed_by, status, note) VALUES (?, ?, ?, ?)')
                ->execute([$applicationId, $studentId, 'submitted', 'Application submitted']);
            $pdo->prepare('INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT user_id, \'New application received\', ?, \'application\', ? FROM tbl_company_members WHERE company_id=? AND status=\'active\'')
                ->execute(['A student has applied for ' . $opportunity['title'] . '.', $applicationId, (int) $opportunity['company_id']]);

            if ($credentialIds) {
                $placeholders = implode(',', array_fill(0, count($credentialIds), '?'));
                $check = $pdo->prepare("SELECT id, original_name, storage_key, mime_type FROM tbl_student_credentials WHERE user_id=? AND id IN ({$placeholders})");
                $check->execute(array_merge([$studentId], $credentialIds));
                $credentials = $check->fetchAll();
                if (count($credentials) !== count($credentialIds)) {
                    throw new RuntimeException('One or more selected documents do not belong to your account.');
                }
                $share = $pdo->prepare('INSERT INTO tbl_application_documents (application_id, credential_id) VALUES (?, ?)');
                $snapshot = $pdo->prepare('INSERT INTO tbl_application_document_snapshots (application_id, credential_id, original_name, storage_key, mime_type, file_size, sha256) VALUES (?, ?, ?, ?, ?, ?, ?)');
                foreach ($credentials as $credential) {
                    $credentialId = (int) $credential['id'];
                    $copy = \CredentialStorage::snapshot((string) $credential['storage_key']);
                    $createdSnapshotKeys[] = $copy['storage_key'];
                    $share->execute([$applicationId, $credentialId]);
                    $snapshot->execute([$applicationId, $credentialId, $credential['original_name'], $copy['storage_key'], $credential['mime_type'], $copy['file_size'], $copy['sha256']]);
                }
            }

            $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'application.submitted', 'application', ?)")
                ->execute([$studentId, $applicationId]);
            });
        } catch (Throwable $exception) {
            foreach ($createdSnapshotKeys as $storageKey) {
                $snapshotPath = \CredentialStorage::path($storageKey);
                if (is_file($snapshotPath)) {
                    unlink($snapshotPath);
                }
            }
            if ($exception instanceof PDOException) {
                $existingApplication->execute([$internshipId, $studentId]);
                if ($existingApplication->fetchColumn()) {
                    return ['page' => 'applications', 'notice' => 'You have already applied for this internship. View it in My Applications.'];
                }
            }
            throw $exception;
        }

        return ['page' => 'applications', 'notice' => 'Your application was submitted.'];
    }

    private function updateBiodataSharing(): array
    {
        \require_role('student');
        $studentId = (int) \auth_user()['id'];
        $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        if (!$applicationId || !in_array($decision, ['share', 'revoke'], true)) {
            throw new RuntimeException('Invalid e-Biodata sharing request.');
        }

        $ownsApplication = \db()->prepare('SELECT COUNT(*) FROM tbl_applications WHERE id=? AND student_id=?');
        $ownsApplication->execute([$applicationId, $studentId]);
        if (!(int) $ownsApplication->fetchColumn()) {
            throw new RuntimeException('You can only manage sharing for your own application.');
        }

        if ($decision === 'share') {
            \db()->prepare('INSERT IGNORE INTO tbl_application_biodata (application_id, student_id) VALUES (?, ?)')
                ->execute([$applicationId, $studentId]);
        } else {
            \db()->prepare('DELETE FROM tbl_application_biodata WHERE application_id=? AND student_id=?')
                ->execute([$applicationId, $studentId]);
        }
        \audit_log($studentId, 'biodata.' . $decision . '_with_application', 'application', $applicationId);

        $notice = $decision === 'share'
            ? 'e-Biodata shared with this company for the application.'
            : 'e-Biodata sharing revoked.';

        return ['page' => 'applications', 'notice' => $notice];
    }

    private function updateApplication(): array
    {
        $reviewerId = (int) \auth_user()['id'];
        $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        $newStatus = (string) ($_POST['status'] ?? '');
        $interviewAtInput = trim((string) ($_POST['interview_at'] ?? ''));
        $interviewLocation = trim((string) ($_POST['interview_location'] ?? ''));
        $interviewAt = null;
        if ($newStatus === 'interview_scheduled') {
            $parsedInterviewAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $interviewAtInput);
            if (!$parsedInterviewAt || $parsedInterviewAt->format('Y-m-d\TH:i') !== $interviewAtInput
                || $parsedInterviewAt <= new DateTimeImmutable() || mb_strlen($interviewLocation) > 255) {
                throw new RuntimeException('Choose a future interview time and enter a location or meeting link no longer than 255 characters.');
            }
            if (str_contains($interviewLocation, '://') && (!filter_var($interviewLocation, FILTER_VALIDATE_URL) || strtolower((string) parse_url($interviewLocation, PHP_URL_SCHEME)) !== 'https')) {
                throw new RuntimeException('Interview meeting links must use HTTPS.');
            }
            $interviewAt = $parsedInterviewAt->format('Y-m-d H:i:s');
        }
        if (!$applicationId || !in_array($newStatus, ['reviewing', 'shortlisted', 'interview_scheduled', 'accepted', 'rejected'], true)) {
            throw new RuntimeException('Invalid application update.');
        }

        $companyId = \require_approved_company($reviewerId);
        \db_transaction(function (PDO $pdo) use ($applicationId, $newStatus, $interviewAt, $interviewLocation, $companyId, $reviewerId): void {
            $stmt = $pdo->prepare('SELECT a.status, a.student_id, i.id AS internship_id, i.title, i.slots FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id = a.internship_id WHERE a.id = ? AND i.company_id = ? FOR UPDATE');
            $stmt->execute([$applicationId, $companyId]);
            $application = $stmt->fetch();
            if (!$application) {
                throw new RuntimeException('Application not found for your company.');
            }

            $allowedTransitions = [
                'submitted' => ['reviewing', 'shortlisted', 'interview_scheduled', 'accepted', 'rejected'],
                'reviewing' => ['shortlisted', 'interview_scheduled', 'accepted', 'rejected'],
                'shortlisted' => ['interview_scheduled', 'accepted', 'rejected'],
                'interview_scheduled' => ['interview_scheduled', 'accepted', 'rejected'],
            ];
            if (!in_array($newStatus, $allowedTransitions[$application['status']] ?? [], true)) {
                throw new RuntimeException('This application can no longer be moved to that status.');
            }

            if ($newStatus === 'accepted') {
                $review = $pdo->prepare("SELECT assessment FROM tbl_application_qualification_reviews WHERE application_id=? ORDER BY id DESC LIMIT 1");
                $review->execute([$applicationId]);
                if ($review->fetchColumn() !== 'qualified') {
                    throw new RuntimeException('Complete and pass the applicant qualification review before accepting this application.');
                }
            }

            if ($newStatus === 'accepted') {
                $accepted = $pdo->prepare("SELECT COUNT(*) FROM tbl_applications WHERE internship_id = ? AND status = 'accepted'");
                $accepted->execute([(int) $application['internship_id']]);
                if ((int) $accepted->fetchColumn() >= (int) $application['slots']) {
                    throw new RuntimeException('All available internship slots have already been filled.');
                }
            }

            $pdo->prepare('UPDATE tbl_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW(), interview_at=IF(?=\'interview_scheduled\', ?, interview_at), interview_location=IF(?=\'interview_scheduled\', ?, interview_location) WHERE id = ?')
                ->execute([$newStatus, $reviewerId, $newStatus, $interviewAt, $newStatus, $interviewLocation ?: null, $applicationId]);
            if ($newStatus === 'accepted') {
                $institutionQuery = $pdo->prepare('SELECT institution_id FROM tbl_student_profiles WHERE user_id=?');
                $institutionQuery->execute([(int) $application['student_id']]);
                $institutionId = $institutionQuery->fetchColumn();
                if ($institutionId) {
                    $coordinatorQuery = $pdo->prepare("SELECT user_id FROM tbl_ojt_coordinators WHERE institution_id=? AND status='active' ORDER BY id LIMIT 1");
                    $coordinatorQuery->execute([(int) $institutionId]);
                    $coordinatorId = $coordinatorQuery->fetchColumn();
                    if (!$coordinatorId) {
                        throw new RuntimeException('An active OJT coordinator must be assigned to the student’s institution before accepting this application.');
                    }
                    $pdo->prepare("INSERT INTO tbl_placements (application_id, coordinator_id, status) VALUES (?, ?, 'pending')")
                        ->execute([$applicationId, (int) $coordinatorId]);
                    $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) VALUES (?, 'Placement approval requested', ?, 'placement', ?)")
                        ->execute([(int) $coordinatorId, 'A company accepted an application for ' . $application['title'] . '. Review the placement details.', (int) $pdo->lastInsertId()]);
                }
            }
            $historyNote = $newStatus === 'interview_scheduled'
                ? 'Interview scheduled for ' . $interviewAt . ($interviewLocation !== '' ? ' at ' . $interviewLocation : '')
                : 'Status updated by employer';
            $pdo->prepare('INSERT INTO tbl_application_status_history (application_id, changed_by, status, note) VALUES (?, ?, ?, ?)')
                ->execute([$applicationId, $reviewerId, $newStatus, $historyNote]);
            $statusLabel = $newStatus === 'rejected' ? 'not selected' : str_replace('_', ' ', $newStatus);
            $pdo->prepare('INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) VALUES (?, ?, ?, \'application\', ?)')
                ->execute([
                    (int) $application['student_id'],
                    $newStatus === 'interview_scheduled' ? 'Interview scheduled' : 'Application status updated',
                    $newStatus === 'interview_scheduled'
                        ? 'Your interview for ' . $application['title'] . ' is scheduled for ' . $interviewAt . ($interviewLocation !== '' ? ' at ' . $interviewLocation : '') . '.'
                        : 'Your application for ' . $application['title'] . ' is now ' . $statusLabel . '.',
                    $applicationId,
                ]);
            $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'application.status_changed', 'application', ?)")
                ->execute([$reviewerId, $applicationId]);
        });

        return ['page' => 'applications', 'notice' => 'Application status updated.'];
    }

    private function withdrawApplication(): array
    {
        \require_role('student');
        $studentId = (int) \auth_user()['id'];
        $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$applicationId) {
            throw new RuntimeException('Select a valid application to withdraw.');
        }

        \db_transaction(function (PDO $pdo) use ($applicationId, $studentId): void {
            $query = $pdo->prepare('SELECT a.status, i.title, i.company_id FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id WHERE a.id=? AND a.student_id=? FOR UPDATE');
            $query->execute([$applicationId, $studentId]);
            $application = $query->fetch();
            if (!$application) {
                throw new RuntimeException('Application not found in your account.');
            }
            if (!in_array($application['status'], ['submitted', 'reviewing', 'shortlisted', 'interview_scheduled'], true)) {
                throw new RuntimeException('This application can no longer be withdrawn.');
            }

            $pdo->prepare("UPDATE tbl_applications SET status='withdrawn', reviewed_at=NOW() WHERE id=?")
                ->execute([$applicationId]);
            $pdo->prepare('INSERT INTO tbl_application_status_history (application_id, changed_by, status, note) VALUES (?, ?, \'withdrawn\', ?)')
                ->execute([$applicationId, $studentId, 'Application withdrawn by student']);
            $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT user_id, 'Application withdrawn', ?, 'application', ? FROM tbl_company_members WHERE company_id=? AND status='active'")
                ->execute(['A student withdrew their application for ' . $application['title'] . '.', $applicationId, (int) $application['company_id']]);
            $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'application.withdrawn', 'application', ?)")
                ->execute([$studentId, $applicationId]);
        });

        return ['page' => 'applications', 'notice' => 'Your application was withdrawn.'];
    }

    private function reviewQualifications(): array
    {
        $reviewerId = (int) \auth_user()['id'];
        $companyId = \require_approved_company($reviewerId);
        $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        $assessment = (string) ($_POST['assessment'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $missingQualifications = trim((string) ($_POST['missing_qualifications'] ?? ''));
        if (!$applicationId || !in_array($assessment, ['qualified', 'needs_improvement', 'not_qualified'], true)
            || $notes === '' || mb_strlen($notes) > 1000 || mb_strlen($missingQualifications) > 3000) {
            throw new RuntimeException('Choose a qualification assessment and provide review notes within the indicated limits.');
        }

        \db_transaction(function (PDO $pdo) use ($applicationId, $assessment, $notes, $missingQualifications, $companyId, $reviewerId): void {
            $application = $pdo->prepare('SELECT a.id FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id WHERE a.id=? AND i.company_id=? FOR UPDATE');
            $application->execute([$applicationId, $companyId]);
            if (!$application->fetchColumn()) {
                throw new RuntimeException('Application not found for your company.');
            }

            $documents = $pdo->prepare(
                'SELECT
                    EXISTS (SELECT 1 FROM tbl_application_biodata WHERE application_id=? AND student_id=(SELECT student_id FROM tbl_applications WHERE id=?))
                    OR EXISTS (SELECT 1 FROM tbl_application_documents WHERE application_id=?)'
            );
            $documents->execute([$applicationId, $applicationId, $applicationId]);
            if (!(bool) $documents->fetchColumn()) {
                throw new RuntimeException('The applicant has not shared an e-Biodata or resume for this application yet.');
            }

            $pdo->prepare('INSERT INTO tbl_application_qualification_reviews (application_id, reviewer_id, assessment, notes, missing_qualifications) VALUES (?, ?, ?, ?, ?)')
                ->execute([$applicationId, $reviewerId, $assessment, $notes, $missingQualifications ?: null]);
            $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'application.qualification_reviewed', 'application', ?)")
                ->execute([$reviewerId, $applicationId]);
        });

        return ['page' => 'applications', 'notice' => 'Applicant qualification review recorded.'];
    }
}
