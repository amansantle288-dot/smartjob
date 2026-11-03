<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Admin\Controllers\AdminController;

foreach (['admin_update_user_status', 'admin_send_notifications'] as $action) {
    if (!AdminController::supports($action)) {
        throw new RuntimeException("Administrator action is not registered: {$action}");
    }
}

$pdo = db();
$pdo->beginTransaction();

try {
    $token = bin2hex(random_bytes(6));
    $insertUser = $pdo->prepare('INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
    $insertUser->execute(['SMARTJOB Admin Test', 'admin-workspace-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), 'admin', 'active']);
    $adminId = (int) $pdo->lastInsertId();
    $insertUser->execute(['SMARTJOB Student Test', 'student-workspace-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), 'student', 'active']);
    $studentId = (int) $pdo->lastInsertId();

    $_SESSION['user_id'] = $adminId;
    $controller = new AdminController();
    $dashboard = $controller->viewData('dashboard');
    $actualUsers = (int) $pdo->query('SELECT COUNT(*) FROM tbl_users')->fetchColumn();
    if ((int) $dashboard['adminStats']['total_users'] !== $actualUsers
        || !isset($dashboard['adminStats']['companies'], $dashboard['adminStats']['pending_companies'])
        || isset($dashboard['internshipStats'], $dashboard['applicationStats'])) {
        throw new RuntimeException('Administrator dashboard data does not match the account-focused contract.');
    }
    foreach (['admin_companies', 'admin_users', 'admin_audit', 'admin_notifications'] as $page) {
        $viewData = $controller->viewData($page);
        if (!$viewData) {
            throw new RuntimeException("Administrator page data is missing for {$page}.");
        }
        ob_start();
        render_page($page, null, [], $viewData);
        $html = (string) ob_get_clean();
        if (!str_contains($html, match ($page) {
            'admin_companies' => 'Company Account Monitoring',
            'admin_users' => 'All user accounts',
            'admin_audit' => 'System Audit Logs',
            'admin_notifications' => 'Company Notification Center',
        })) {
            throw new RuntimeException("Administrator page failed to render its expected heading: {$page}.");
        }
    }
    ob_start();
    render_page('dashboard', null, [], $dashboard);
    $dashboardHtml = (string) ob_get_clean();
    if (!str_contains($dashboardHtml, 'Administrator dashboard')
        || preg_match('/Internship posts|Applications received|Placement statistics/i', $dashboardHtml) === 1) {
        throw new RuntimeException('Administrator dashboard contains missing or out-of-scope content.');
    }
    ob_start();
    render_page('account_settings', null, [], []);
    $accountHtml = (string) ob_get_clean();
    if (!str_contains($accountHtml, 'id="account-profile"') || !str_contains($accountHtml, 'Account settings')) {
        throw new RuntimeException('Administrator profile and settings did not render.');
    }

    $companyId = (int) $pdo->query("SELECT c.id FROM tbl_companies c JOIN tbl_company_members m ON m.company_id=c.id AND m.member_role='owner' JOIN tbl_users u ON u.id=m.user_id ORDER BY c.id LIMIT 1")->fetchColumn();
    if ($companyId > 0) {
        $_GET = ['company_id' => $companyId];
        ob_start();
        render_page('companies', null, [], []);
        $verificationHtml = (string) ob_get_clean();
        if (!str_contains($verificationHtml, 'Verification history')
            || !str_contains($verificationHtml, 'id="company-' . $companyId . '"')) {
            throw new RuntimeException('Company verification deep links or history did not render.');
        }
    }

    $_SESSION['user_id'] = $studentId;
    try {
        $controller->viewData('admin_users');
        throw new RuntimeException('A non-administrator accessed user management.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'You are not authorized to perform this action.') {
            throw $exception;
        }
    }

    $_SESSION['user_id'] = $adminId;
    $_POST = ['user_id' => $studentId, 'status' => 'active'];
    $noOp = $controller->handle('admin_update_user_status');
    if (!str_contains($noOp['notice'], 'already has that status')) {
        throw new RuntimeException('An unchanged account status was not handled as a no-op.');
    }

    $_POST = ['user_id' => $studentId, 'status' => 'suspended'];
    $controller->handle('admin_update_user_status');
    $status = $pdo->prepare('SELECT status FROM tbl_users WHERE id=?');
    $status->execute([$studentId]);
    if ($status->fetchColumn() !== 'suspended') {
        throw new RuntimeException('Administrator account status change did not persist within the transaction.');
    }
    $audit = $pdo->prepare("SELECT description FROM tbl_audit_logs WHERE user_id=? AND action='account.status.changed' ORDER BY id DESC LIMIT 1");
    $audit->execute([$adminId]);
    if (!str_contains((string) $audit->fetchColumn(), 'active to suspended')) {
        throw new RuntimeException('Account status changes were not described in the audit log.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_GET = [];
    $_POST = [];
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "Administrator workspace access, data, and account action checks passed.\n";
