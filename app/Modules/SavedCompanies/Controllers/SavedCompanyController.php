<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\SavedCompanies\Controllers;

use PDO;
use RuntimeException;

final class SavedCompanyController
{
    public static function supports(string $action): bool
    {
        return in_array($action, ['save_company', 'unsave_company'], true);
    }

    /** @return array{page: string, notice: string} */
    public function handle(string $action): array
    {
        if (!self::supports($action)) {
            throw new RuntimeException('Unknown saved company action.');
        }

        \require_role('student');
        $studentId = (int) \auth_user()['id'];
        $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
        $returnPage = (string) ($_POST['return_page'] ?? 'saved_companies');
        if (!$companyId) {
            throw new RuntimeException('Select a valid company.');
        }
        if (!in_array($returnPage, ['explore', 'saved_companies'], true)) {
            throw new RuntimeException('Invalid return page.');
        }

        if ($action === 'save_company') {
            \db_transaction(function (PDO $pdo) use ($studentId, $companyId): void {
                $company = $pdo->prepare("SELECT id FROM tbl_companies WHERE id=? AND verification_status='approved' FOR UPDATE");
                $company->execute([$companyId]);
                if (!$company->fetchColumn()) {
                    throw new RuntimeException('This company profile is no longer available to save.');
                }

                $save = $pdo->prepare('INSERT IGNORE INTO tbl_saved_companies (student_id, company_id) VALUES (?, ?)');
                $save->execute([$studentId, $companyId]);
                if ($save->rowCount() > 0) {
                    \audit_log($studentId, 'company.saved', 'company', $companyId);
                }
            });
            return ['page' => $returnPage, 'notice' => 'Company saved to your interested companies.'];
        }

        \db_transaction(function (PDO $pdo) use ($studentId, $companyId): void {
            $remove = $pdo->prepare('DELETE FROM tbl_saved_companies WHERE student_id=? AND company_id=?');
            $remove->execute([$studentId, $companyId]);
            if ($remove->rowCount() > 0) {
                \audit_log($studentId, 'company.unsaved', 'company', $companyId);
            }
        });
        return ['page' => $returnPage, 'notice' => 'Company removed from your interested companies.'];
    }
}
