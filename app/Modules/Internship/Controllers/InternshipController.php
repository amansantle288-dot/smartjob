<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Internship\Controllers;

use PDO;
use RuntimeException;

final class InternshipController
{
    public static function supports(string $action): bool
    {
        return in_array($action, ['create_internship', 'update_internship', 'delete_internship'], true);
    }

    /**
     * @return array{page: string, notice: string}
     */
    public function handle(string $action): array
    {
        return match ($action) {
            'create_internship' => $this->createInternship(),
            'update_internship' => $this->updateInternship(),
            'delete_internship' => $this->closeInternship(),
            default => throw new RuntimeException('Unknown internship action.'),
        };
    }

    private function createInternship(): array
    {
        [$userId, $companyId] = $this->approvedEmployer();
        $publishedAt = \HiringCountdown::publishTimestamp();
        [$title, $category, $description, $requirements, $location, $workMode, $slots, $durationDays] = $this->validatedFields();
        $deadline = \HiringCountdown::deadlineFromDuration($publishedAt, $durationDays);

        $stmt = \db()->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, category, description, requirements, location, work_mode, slots, application_deadline, published_at, hiring_duration_days, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published')");
        $stmt->execute([$companyId, $userId, $title, $category ?: null, $description, $requirements ?: null, $location ?: null, $workMode, $slots, $deadline, $publishedAt, $durationDays]);
        $internshipId = (int) \db()->lastInsertId();
        \audit_log($userId, 'internship.created', 'internship', $internshipId);

        return ['page' => 'internships', 'notice' => 'Internship published.'];
    }

    private function updateInternship(): array
    {
        [$userId, $companyId] = $this->approvedEmployer('editing');
        $internshipId = filter_var($_POST['internship_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$internshipId) {
            throw new RuntimeException('Select a valid internship to update.');
        }
        $auditAction = 'internship.updated';
        $extendedOnReopen = false;
        \db_transaction(function (PDO $pdo) use ($internshipId, $companyId, &$auditAction, &$extendedOnReopen): void {
            $existing = $pdo->prepare('SELECT id, published_at, hiring_duration_days, application_deadline, status FROM tbl_internship_opportunities WHERE id=? AND company_id=? FOR UPDATE');
            $existing->execute([$internshipId, $companyId]);
            $current = $existing->fetch();
            if (!$current) {
                throw new RuntimeException('This internship is not available to edit.');
            }

            [$title, $category, $description, $requirements, $location, $workMode, $slots, $durationDays] = $this->validatedFields((int) $current['hiring_duration_days']);
            $postingStatus = $_POST['posting_status'] ?? $current['status'];
            if (!is_string($postingStatus) || !in_array($postingStatus, ['draft', 'published', 'paused', 'closed'], true)) {
                throw new RuntimeException('Choose a valid internship hiring status.');
            }
            $reopeningExpired = $postingStatus === 'published'
                && ($current['status'] !== 'published' || array_key_exists('posting_status', $_POST))
                && (string) $current['application_deadline'] < \HiringCountdown::today();
            $deadlineBase = $reopeningExpired ? \HiringCountdown::today() : (string) $current['published_at'];
            $deadline = $durationDays === (int) $current['hiring_duration_days'] && !$reopeningExpired
                ? (string) $current['application_deadline']
                : \HiringCountdown::deadlineFromDuration($deadlineBase, $durationDays);

            $update = $pdo->prepare('UPDATE tbl_internship_opportunities SET title=?, category=?, description=?, requirements=?, location=?, work_mode=?, slots=?, application_deadline=?, hiring_duration_days=?, status=? WHERE id=? AND company_id=?');
            $update->execute([$title, $category ?: null, $description, $requirements ?: null, $location ?: null, $workMode, $slots, $deadline, $durationDays, $postingStatus, $internshipId, $companyId]);
            if ($reopeningExpired) {
                $auditAction = 'internship.reopened';
                $extendedOnReopen = $deadline > $current['application_deadline'];
            } elseif ($postingStatus !== $current['status']) {
                $auditAction = match ($postingStatus) {
                    'published' => 'internship.reopened',
                    'paused' => 'internship.paused',
                    'closed' => 'internship.closed',
                    default => 'internship.drafted',
                };
            } elseif ($deadline !== $current['application_deadline']) {
                $auditAction = $deadline > $current['application_deadline'] ? 'internship.deadline_extended' : 'internship.deadline_changed';
            }
        });

        \audit_log($userId, $auditAction, 'internship', $internshipId);
        if ($extendedOnReopen) {
            \audit_log($userId, 'internship.deadline_extended', 'internship', $internshipId);
        }
        return ['page' => 'internships', 'notice' => 'Internship updated.'];
    }

    private function closeInternship(): array
    {
        $userId = (int) \auth_user()['id'];
        $companyId = \require_approved_company($userId, 'closing');
        $internshipId = filter_var($_POST['internship_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$internshipId) {
            throw new RuntimeException('Invalid internship selected for closure.');
        }

        $stmt = \db()->prepare("UPDATE tbl_internship_opportunities SET status = 'closed' WHERE id = ? AND company_id = ? AND status IN ('published', 'paused', 'draft')");
        $stmt->execute([$internshipId, $companyId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('This internship could not be closed because it was not found for your company.');
        }

        \audit_log($userId, 'internship.closed', 'internship', $internshipId);
        return ['page' => 'internships', 'notice' => 'Internship closed and no longer accepting applications.'];
    }

    private function approvedEmployer(string $action = 'publishing'): array
    {
        $userId = (int) \auth_user()['id'];
        $companyId = \require_approved_company($userId, $action);
        return [$userId, $companyId];
    }

    private function validatedFields(?int $existingDuration = null): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $requirements = trim((string) ($_POST['requirements'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $workMode = (string) ($_POST['work_mode'] ?? 'onsite');
        $slots = filter_var($_POST['slots'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        if ($title === '' || mb_strlen($title) > 180 || mb_strlen($category) > 100 || $description === '' || mb_strlen($description) > 12000 || mb_strlen($requirements) > 5000 || mb_strlen($location) > 180 || !in_array($workMode, ['onsite', 'hybrid', 'remote'], true) || !$slots) {
            throw new RuntimeException('Complete the required fields using the indicated limits.');
        }

        $durationDays = $this->validatedDuration($existingDuration);

        return [$title, $category, $description, $requirements, $location, $workMode, $slots, $durationDays];
    }

    private function validatedDuration(?int $existingDuration): int
    {
        $presetValue = $_POST['hiring_duration_preset'] ?? '';
        $preset = is_string($presetValue) ? $presetValue : '';
        $customDuration = $_POST['custom_hiring_duration_days'] ?? null;
        if ($preset === '') {
            $customDuration = $_POST['hiring_duration_days'] ?? $customDuration;
            $preset = $customDuration === null || $customDuration === '' ? '30' : 'custom';
        }
        if ($preset === 'custom') {
            $duration = filter_var($customDuration, FILTER_VALIDATE_INT);
            $legacyUnchangedDuration = $existingDuration !== null && $existingDuration > 90 && $duration === $existingDuration;
            if ($duration === false || $duration < 1 || ($duration > 90 && !$legacyUnchangedDuration)) {
                throw new RuntimeException('Choose a custom hiring duration from 1 to 90 days.');
            }
            return $duration;
        }

        $duration = filter_var($preset, FILTER_VALIDATE_INT);
        if ($duration === false || !in_array($duration, [7, 15, 30, 45, 60, 90], true)) {
            throw new RuntimeException('Choose one of the available hiring durations or a valid custom duration.');
        }
        return $duration;
    }
}
