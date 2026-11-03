<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Placements\Controllers;

use PDO;
use RuntimeException;

final class PlacementController
{
    public static function supports(string $action): bool
    {
        return $action === 'approve_placement';
    }

    public function handle(string $action): array
    {
        if ($action !== 'approve_placement') {
            throw new RuntimeException('Unknown placement action.');
        }

        \require_role('coordinator');
        $coordinatorId = (int) \auth_user()['id'];
        $placementId = filter_var($_POST['placement_id'] ?? null, FILTER_VALIDATE_INT);
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $hours = filter_var($_POST['required_hours'] ?? null, FILTER_VALIDATE_INT);
        if (!$placementId || !\valid_date($startDate) || !\valid_date($endDate) || $endDate < $startDate || !$hours || $hours > 65535) {
            throw new RuntimeException('Enter valid placement dates and required hours between 1 and 65,535.');
        }

        \db_transaction(function (PDO $pdo) use ($placementId, $coordinatorId, $startDate, $endDate, $hours): void {
            $coordinator = $pdo->prepare("SELECT institution_id FROM tbl_ojt_coordinators WHERE user_id=? AND status='active'");
            $coordinator->execute([$coordinatorId]);
            $institutionId = $coordinator->fetchColumn();
            if (!$institutionId) {
                throw new RuntimeException('Your coordinator account is not assigned to an active institution.');
            }
            $placementQuery = $pdo->prepare(
                'SELECT p.status, a.student_id, a.id AS application_id, i.title, i.company_id
                 FROM tbl_placements p
                 JOIN tbl_applications a ON a.id=p.application_id
                 JOIN tbl_internship_opportunities i ON i.id=a.internship_id
                 JOIN tbl_student_profiles sp ON sp.user_id=a.student_id
                 WHERE p.id=? AND sp.institution_id=? FOR UPDATE'
            );
            $placementQuery->execute([$placementId, $institutionId]);
            $placement = $placementQuery->fetch();
            if (!$placement) {
                throw new RuntimeException('Placement not found in your assigned institution.');
            }
            if ($placement['status'] !== 'pending') {
                throw new RuntimeException('This placement has already been reviewed.');
            }

            $pdo->prepare("UPDATE tbl_placements SET status='active', coordinator_id=?, start_date=?, end_date=?, required_hours=? WHERE id=?")
                ->execute([$coordinatorId, $startDate, $endDate, $hours, $placementId]);
            $message = 'Your placement for ' . $placement['title'] . ' was approved for ' . $startDate . ' through ' . $endDate . '.';
            $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) VALUES (?, 'Placement approved', ?, 'application', ?)")
                ->execute([(int) $placement['student_id'], $message, (int) $placement['application_id']]);
            $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id) SELECT user_id, 'Placement approved', ?, 'application', ? FROM tbl_company_members WHERE company_id=? AND status='active'")
                ->execute([$message, (int) $placement['application_id'], (int) $placement['company_id']]);
            $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'placement.approved', 'placement', ?)")
                ->execute([$coordinatorId, $placementId]);
        });

        return ['page' => 'placements', 'notice' => 'Placement approved and activated.'];
    }
}
