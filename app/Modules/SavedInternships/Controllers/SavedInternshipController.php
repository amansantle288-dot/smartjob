<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SavedInternships\Controllers;

use RuntimeException;

final class SavedInternshipController
{
    public static function supports(string $action): bool
    {
        return in_array($action, ['save_internship', 'unsave_internship'], true);
    }

    /** @return array{page: string, notice: string} */
    public function handle(string $action): array
    {
        if (!self::supports($action)) {
            throw new RuntimeException('Unknown saved internship action.');
        }

        \require_role('student');
        $studentId = (int) \auth_user()['id'];
        $internshipId = filter_var($_POST['internship_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$internshipId) {
            throw new RuntimeException('Select a valid internship.');
        }

        $returnPage = (string) ($_POST['return_page'] ?? 'saved');
        if (!in_array($returnPage, ['explore', 'saved'], true)) {
            throw new RuntimeException('Invalid return page.');
        }

        if ($action === 'save_internship') {
            \db_transaction(function (\PDO $pdo) use ($studentId, $internshipId): void {
                $opportunity = $pdo->prepare("SELECT i.id FROM tbl_internship_opportunities i JOIN tbl_companies c ON c.id=i.company_id WHERE i.id=? AND i.status='published' AND i.application_deadline>=CURDATE() AND c.verification_status='approved' FOR UPDATE");
                $opportunity->execute([$internshipId]);
                if (!$opportunity->fetchColumn()) {
                    throw new RuntimeException('This opportunity is no longer available to save.');
                }

                $save = $pdo->prepare('INSERT IGNORE INTO tbl_saved_internships (student_id, internship_id) VALUES (?, ?)');
                $save->execute([$studentId, $internshipId]);
                if ($save->rowCount() > 0) {
                    \audit_log($studentId, 'internship.saved', 'internship', $internshipId);
                }
            });
            return ['page' => $returnPage, 'notice' => 'Internship saved to your list.'];
        }

        \db_transaction(function (\PDO $pdo) use ($studentId, $internshipId): void {
            $remove = $pdo->prepare('DELETE FROM tbl_saved_internships WHERE student_id=? AND internship_id=?');
            $remove->execute([$studentId, $internshipId]);
            if ($remove->rowCount() > 0) {
                \audit_log($studentId, 'internship.unsaved', 'internship', $internshipId);
            }
        });
        return ['page' => $returnPage, 'notice' => 'Internship removed from your saved list.'];
    }
}
