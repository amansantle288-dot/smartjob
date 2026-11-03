<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Training\Controllers;

use PDO;
use RuntimeException;

final class TrainingController
{
    private const ACTIONS = ['add_training', 'verify_training', 'add_certification'];

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
            'add_training' => $this->addTraining(),
            'verify_training' => $this->verifyTraining(),
            'add_certification' => $this->addCertification(),
            default => throw new RuntimeException('Unknown training action.'),
        };
    }

    private function addTraining(): array
    {
        $userId = $this->studentId();
        $title = trim((string) ($_POST['training_title'] ?? ''));
        $provider = trim((string) ($_POST['provider'] ?? ''));
        $category = trim((string) ($_POST['training_category'] ?? ''));
        $description = trim((string) ($_POST['training_description'] ?? ''));
        $hours = filter_var($_POST['duration_hours'] ?? null, FILTER_VALIDATE_FLOAT);
        $credentialId = filter_var($_POST['training_credential_id'] ?? null, FILTER_VALIDATE_INT);
        $startDate = trim((string) ($_POST['training_start_date'] ?? ''));
        $completionDate = trim((string) ($_POST['training_completion_date'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180 || mb_strlen($provider) > 180 || mb_strlen($category) > 100 || mb_strlen($description) > 3000 || ($hours !== false && ($hours < 0 || $hours > 10000)) || ($startDate !== '' && !\valid_date($startDate)) || ($completionDate !== '' && !\valid_date($completionDate))) {
            throw new RuntimeException('Check the training title, details, and duration.');
        }

        if ($credentialId) {
            $owns = \db()->prepare("SELECT COUNT(*) FROM tbl_student_credentials WHERE id=? AND user_id=? AND category IN ('training_certificate','seminar_certificate')");
            $owns->execute([$credentialId, $userId]);
            if (!(int) $owns->fetchColumn()) {
                throw new RuntimeException('Select a certificate that belongs to your account.');
            }
        }

        $skills = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($_POST['acquired_skills'] ?? ''))))));
        if (count($skills) > 20 || array_filter($skills, static fn ($skill): bool => mb_strlen($skill) > 120)) {
            throw new RuntimeException('Enter at most 20 acquired skills, each 120 characters or fewer.');
        }

        $status = $credentialId ? 'pending' : 'self_reported';
        \db()->prepare('INSERT INTO tbl_student_trainings (user_id, credential_id, title, category, provider, description, start_date, completion_date, duration_hours, acquired_skills, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $credentialId ?: null, $title, $category ?: null, $provider ?: null, $description ?: null, $startDate ?: null, $completionDate ?: null, $hours !== false ? $hours : null, json_encode($skills, JSON_THROW_ON_ERROR), $status]);

        $notice = $credentialId ? 'Training record submitted for coordinator verification.' : 'Training record saved as self-reported.';
        return ['page' => 'training', 'notice' => $notice];
    }

    private function verifyTraining(): array
    {
        $reviewerId = $this->coordinatorId();
        $trainingId = filter_var($_POST['training_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (!$trainingId || !in_array($decision, ['verified', 'rejected'], true) || mb_strlen($notes) > 1000) {
            throw new RuntimeException('Invalid training verification decision.');
        }

        $coordinator = \db()->prepare('SELECT institution_id FROM tbl_ojt_coordinators WHERE user_id = ? AND status = ?');
        $coordinator->execute([$reviewerId, 'active']);
        $institutionId = $coordinator->fetchColumn();
        if (!$institutionId) {
            throw new RuntimeException('Your coordinator account is not assigned to an institution.');
        }

        \db_transaction(function (PDO $pdo) use ($trainingId, $decision, $notes, $institutionId, $reviewerId): void {
            $stmt = $pdo->prepare("SELECT t.id FROM tbl_student_trainings t JOIN tbl_student_profiles sp ON sp.user_id=t.user_id WHERE t.id=? AND sp.institution_id=? AND t.credential_id IS NOT NULL AND t.verification_status='pending' FOR UPDATE");
            $stmt->execute([$trainingId, $institutionId]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Training record not found in your institution or already reviewed.');
            }

            $pdo->prepare('UPDATE tbl_student_trainings SET verification_status=? WHERE id=?')->execute([$decision, $trainingId]);
            $pdo->prepare('INSERT INTO tbl_training_verifications (training_id, reviewer_id, status, notes) VALUES (?, ?, ?, ?)')
                ->execute([$trainingId, $reviewerId, $decision, $notes ?: null]);
            $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT user_id, 'Training record reviewed', ?, 'training', ? FROM tbl_student_trainings WHERE id=?")
                ->execute(['Training evidence was ' . $decision . '.', $trainingId, $trainingId]);
            $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, 'training', ?)")
                ->execute([$reviewerId, 'training.' . $decision, $trainingId]);
        });

        return ['page' => 'training', 'notice' => 'Training evidence review recorded.'];
    }

    private function addCertification(): array
    {
        $userId = $this->studentId();
        $title = trim((string) ($_POST['certification_title'] ?? ''));
        $issuer = trim((string) ($_POST['issuer'] ?? ''));
        $number = trim((string) ($_POST['credential_number'] ?? ''));
        $credentialId = filter_var($_POST['certification_credential_id'] ?? null, FILTER_VALIDATE_INT);
        $issued = trim((string) ($_POST['certification_issued_at'] ?? ''));
        $expires = trim((string) ($_POST['certification_expires_at'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180 || mb_strlen($issuer) > 180 || mb_strlen($number) > 120 || ($issued !== '' && !\valid_date($issued)) || ($expires !== '' && !\valid_date($expires))) {
            throw new RuntimeException('Check the certification details and dates.');
        }

        if ($credentialId) {
            $owns = \db()->prepare('SELECT COUNT(*) FROM tbl_student_credentials WHERE id=? AND user_id=?');
            $owns->execute([$credentialId, $userId]);
            if (!(int) $owns->fetchColumn()) {
                throw new RuntimeException('Select a credential that belongs to your account.');
            }
        }

        \db()->prepare("INSERT INTO tbl_student_certifications (user_id, credential_id, title, issuer, issued_at, expires_at, credential_number, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $credentialId ?: null, $title, $issuer ?: null, $issued ?: null, $expires ?: null, $number ?: null, 'self_reported']);

        return ['page' => 'training', 'notice' => 'Certification record saved as self-reported.'];
    }

    private function studentId(): int
    {
        \require_role('student');

        return (int) \auth_user()['id'];
    }

    private function coordinatorId(): int
    {
        \require_role('coordinator');

        return (int) \auth_user()['id'];
    }
}
