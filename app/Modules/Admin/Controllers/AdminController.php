<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Admin\Controllers;

use RuntimeException;
use SMARTJOB\Modules\AccountVerification\Services\ProviderException;
use SMARTJOB\Modules\SmartMail\Services\EmailService;

final class AdminController
{
    private const PAGE_SIZE = 25;

    private const NOTIFICATION_TEMPLATES = [
        'welcome' => 'Welcome message',
        'verification_pending' => 'Verification pending',
        'verification_approved' => 'Verification approved',
        'verification_rejected' => 'Verification rejected',
        'missing_requirements' => 'Missing requirements',
        'account_reminder' => 'Account-related reminder',
        'custom' => 'Custom administrative announcement',
    ];

    public static function supports(string $action): bool
    {
        return in_array($action, ['admin_update_user_status', 'admin_send_notifications'], true);
    }

    /** @return array<string, mixed> */
    public function viewData(string $page): array
    {
        \require_role('admin');
        return match ($page) {
            'dashboard' => $this->dashboardData(),
            'admin_companies' => $this->companyData(),
            'admin_users' => $this->userData(),
            'admin_audit' => $this->auditData(),
            'admin_notifications' => $this->notificationData(),
            default => [],
        };
    }

    /** @return array{page: string, notice: string} */
    public function handle(string $action): array
    {
        \require_role('admin');
        return match ($action) {
            'admin_update_user_status' => $this->updateUserStatus(),
            'admin_send_notifications' => $this->sendNotifications(),
            default => throw new RuntimeException('Unknown administrator action.'),
        };
    }

    public function exportAuditCsv(): never
    {
        \require_role('admin');
        $filters = $this->auditFilters();
        $stmt = \db()->prepare($filters['sql'] . ' ORDER BY a.created_at DESC, a.id DESC LIMIT 5000');
        $stmt->execute($filters['params']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="smartjob-audit-logs.csv"');
        header('Cache-Control: no-store');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('The audit log export could not be opened.');
        }
        fputcsv($output, ['Timestamp', 'Actor', 'Role', 'Action', 'Module', 'Target', 'Result', 'Description']);
        foreach ($stmt->fetchAll() as $row) {
            fputcsv($output, [
                $this->csvCell($row['created_at']),
                $this->csvCell($row['actor_name'] ?: 'System / unavailable'),
                $this->csvCell($row['actor_role'] ?: 'system'),
                $this->csvCell($row['action']),
                $this->csvCell($row['entity_type'] ?: 'system'),
                $this->csvCell($row['entity_id'] ?? ''),
                $this->csvCell($row['result']),
                $this->csvCell($row['description'] ?: $row['action']),
            ]);
        }
        fclose($output);
        exit;
    }

    private function dashboardData(): array
    {
        $counts = \db()->query(
            "SELECT
                (SELECT COUNT(*) FROM tbl_users) AS total_users,
                (SELECT COUNT(*) FROM tbl_users WHERE role='student') AS students,
                (SELECT COUNT(*) FROM tbl_companies) AS companies,
                (SELECT COUNT(*) FROM tbl_companies WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_companies,
                (SELECT COUNT(*) FROM tbl_companies WHERE verification_status='pending') AS pending_companies,
                (SELECT COUNT(*) FROM tbl_companies WHERE verification_status='approved') AS verified_companies,
                (SELECT COUNT(*) FROM tbl_notifications WHERE user_id=" . (int) \auth_user()['id'] . " AND read_at IS NULL) AS unread_notifications,
                (SELECT COUNT(*) FROM tbl_login_attempts WHERE succeeded=0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS security_events"
        )->fetch();

        $recent = \db()->query(
            "SELECT a.id, a.action, a.entity_type, a.entity_id, a.result, a.description, a.created_at, u.full_name AS actor_name, COALESCE(a.actor_role, u.role, 'system') AS actor_role
             FROM tbl_audit_logs a
             LEFT JOIN tbl_users u ON u.id=a.user_id
             WHERE (
                   a.action LIKE 'auth.%'
                   OR a.action LIKE 'account.%'
                   OR a.action LIKE 'company.%'
                   OR a.action LIKE 'company_document.%'
                   OR a.action LIKE 'admin.%'
                   OR a.action LIKE 'security.%'
                   OR a.action LIKE 'role.%'
               )
             ORDER BY a.created_at DESC, a.id DESC LIMIT 8"
        )->fetchAll();
        $pending = \db()->query(
            "SELECT c.id, c.name, c.created_at, c.verification_status, u.email AS owner_email
             FROM tbl_companies c
             LEFT JOIN tbl_company_members m ON m.company_id=c.id AND m.member_role='owner'
             LEFT JOIN tbl_users u ON u.id=m.user_id
             WHERE c.verification_status='pending'
             ORDER BY c.created_at DESC, c.id DESC LIMIT 6"
        )->fetchAll();

        return ['adminStats' => $counts, 'adminRecentActivity' => $recent, 'adminPendingCompanies' => $pending];
    }

    private function companyData(): array
    {
        $filters = [];
        $params = [];
        $query = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 120));
        if ($query !== '') {
            $filters[] = '(c.name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)';
            array_push($params, '%' . $query . '%', '%' . $query . '%', '%' . $query . '%');
        }
        $verification = (string) ($_GET['verification'] ?? '');
        if (in_array($verification, ['pending', 'approved', 'rejected', 'suspended'], true)) {
            $filters[] = 'c.verification_status=?';
            $params[] = $verification;
        }
        $accountStatus = (string) ($_GET['status'] ?? '');
        if (in_array($accountStatus, ['active', 'inactive', 'suspended', 'pending_verification'], true)) {
            $filters[] = 'u.status=?';
            $params[] = $accountStatus;
        }
        if ((string) ($_GET['new'] ?? '') === '1') {
            $filters[] = "c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }
        $where = $filters ? ' WHERE ' . implode(' AND ', $filters) : '';
        $page = $this->pageNumber();
        $offset = ($page - 1) * self::PAGE_SIZE;
        $count = \db()->prepare(
            'SELECT COUNT(DISTINCT c.id) FROM tbl_companies c
             LEFT JOIN tbl_company_members m ON m.company_id=c.id AND m.member_role=\'owner\'
             LEFT JOIN tbl_users u ON u.id=m.user_id' . $where
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $stmt = \db()->prepare(
            "SELECT c.id, c.name, c.company_type, c.verification_status, c.created_at,
                    u.id AS owner_id, u.email, u.phone_number, u.status AS account_status, u.last_login_at,
                    v.status AS review_status,
                    (SELECT e.delivery_status FROM tbl_admin_email_logs e WHERE e.company_id=c.id ORDER BY e.id DESC LIMIT 1) AS notification_status
             FROM tbl_companies c
             LEFT JOIN tbl_company_members m ON m.company_id=c.id AND m.member_role='owner'
             LEFT JOIN tbl_users u ON u.id=m.user_id
             LEFT JOIN tbl_company_verifications v ON v.company_id=c.id" . $where . '
             ORDER BY c.created_at DESC, c.id DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return [
            'adminCompanies' => $stmt->fetchAll(),
            'adminCompanyTotal' => $total,
            'adminCompanyPage' => $page,
            'adminCompanyPageCount' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'adminCompanyFilters' => ['q' => $query, 'verification' => $verification, 'status' => $accountStatus, 'new' => (string) ($_GET['new'] ?? '')],
        ];
    }

    private function userData(): array
    {
        $filters = [];
        $params = [];
        $query = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 120));
        if ($query !== '') {
            $filters[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
            array_push($params, '%' . $query . '%', '%' . $query . '%');
        }
        $role = (string) ($_GET['role'] ?? '');
        if (in_array($role, ['student', 'employer', 'coordinator', 'admin'], true)) {
            $filters[] = 'u.role=?';
            $params[] = $role;
        }
        $status = (string) ($_GET['status'] ?? '');
        if (in_array($status, ['active', 'inactive', 'suspended', 'pending_verification'], true)) {
            $filters[] = 'u.status=?';
            $params[] = $status;
        }
        $where = $filters ? ' WHERE ' . implode(' AND ', $filters) : '';
        $page = $this->pageNumber();
        $offset = ($page - 1) * self::PAGE_SIZE;
        $count = \db()->prepare('SELECT COUNT(*) FROM tbl_users u' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $stmt = \db()->prepare(
            'SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at, u.last_login_at,
                    (SELECT COUNT(*) FROM tbl_login_attempts l WHERE l.email=u.email AND l.succeeded=0 AND l.attempted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS failed_logins
             FROM tbl_users u' . $where . '
             ORDER BY u.created_at DESC, u.id DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        return [
            'adminUsers' => $stmt->fetchAll(),
            'adminUserTotal' => $total,
            'adminUserPage' => $page,
            'adminUserPageCount' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'adminUserFilters' => ['q' => $query, 'role' => $role, 'status' => $status],
        ];
    }

    private function auditData(): array
    {
        $filters = $this->auditFilters();
        $count = \db()->prepare(
            'SELECT COUNT(*) FROM tbl_audit_logs a LEFT JOIN tbl_users u ON u.id=a.user_id' . $filters['where']
        );
        $count->execute($filters['params']);
        $total = (int) $count->fetchColumn();
        $page = $this->pageNumber();
        $offset = ($page - 1) * self::PAGE_SIZE;
        $stmt = \db()->prepare(
            $filters['sql'] . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset
        );
        $stmt->execute($filters['params']);
        $security = \db()->query(
            'SELECT email, succeeded, attempted_at FROM tbl_login_attempts ORDER BY attempted_at DESC, id DESC LIMIT 50'
        )->fetchAll();
        return [
            'adminAuditRows' => $stmt->fetchAll(),
            'adminAuditTotal' => $total,
            'adminAuditPage' => $page,
            'adminAuditPageCount' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'adminAuditFilters' => $filters['values'],
            'adminSecurityEvents' => $security,
        ];
    }

    /** @return array{sql: string, where: string, params: array<int, string>, values: array<string, string>} */
    private function auditFilters(): array
    {
        $filters = ["(a.entity_type NOT IN ('internship','application','placement') OR a.entity_type IS NULL)", "a.action NOT LIKE 'internship.%'", "a.action NOT LIKE 'application.%'", "a.action NOT LIKE 'placement.%'"];
        $params = [];
        $query = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 120));
        if ($query !== '') {
            $filters[] = '(a.action LIKE ? OR a.description LIKE ? OR u.full_name LIKE ? OR a.entity_type LIKE ?)';
            array_push($params, '%' . $query . '%', '%' . $query . '%', '%' . $query . '%', '%' . $query . '%');
        }
        $action = trim(mb_substr((string) ($_GET['action_filter'] ?? ''), 0, 100));
        if ($action !== '') {
            $filters[] = 'a.action=?';
            $params[] = $action;
        }
        $result = (string) ($_GET['result'] ?? '');
        if (in_array($result, ['success', 'failure'], true)) {
            $filters[] = 'a.result=?';
            $params[] = $result;
        }
        $role = (string) ($_GET['role'] ?? '');
        if (in_array($role, ['student', 'employer', 'coordinator', 'admin', 'system'], true)) {
            $filters[] = "COALESCE(a.actor_role,u.role,'system')=?";
            $params[] = $role;
        }
        $where = ' WHERE ' . implode(' AND ', $filters);
        return [
            'sql' => "SELECT a.id, a.action, a.entity_type, a.entity_id, a.result, a.description, a.created_at, u.full_name AS actor_name, COALESCE(a.actor_role,u.role,'system') AS actor_role FROM tbl_audit_logs a LEFT JOIN tbl_users u ON u.id=a.user_id" . $where,
            'where' => $where,
            'params' => $params,
            'values' => ['q' => $query, 'action_filter' => $action, 'result' => $result, 'role' => $role],
        ];
    }

    private function csvCell(mixed $value): string
    {
        $cell = (string) $value;
        if ($cell !== '' && preg_match('/\A[\s\x00-\x1F]*[=+\-@]/', $cell) === 1) {
            return "'" . $cell;
        }
        return $cell;
    }

    private function notificationData(): array
    {
        $companies = \db()->query('SELECT id, name, created_at FROM tbl_companies ORDER BY created_at DESC, id DESC')->fetchAll();
        $selected = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_GET['company_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        $template = (string) ($_GET['template'] ?? '');
        if (!isset(self::NOTIFICATION_TEMPLATES[$template])) {
            $template = '';
        }
        $body = $this->notificationBody($template, (string) ($_GET['custom_message'] ?? ''));
        $subject = $this->notificationSubject($template);
        $history = \db()->query(
            'SELECT e.id, e.company_id, e.company_name, e.recipient_email, e.template_key, e.subject, e.delivery_status, e.provider_name, e.http_status_code, e.error_code, e.created_at, u.full_name AS sent_by_name
             FROM tbl_admin_email_logs e LEFT JOIN tbl_users u ON u.id=e.sent_by
             ORDER BY e.created_at DESC, e.id DESC LIMIT 100'
        )->fetchAll();
        return [
            'adminNotificationCompanies' => $companies,
            'adminNotificationSelected' => $selected,
            'adminNotificationTemplates' => self::NOTIFICATION_TEMPLATES,
            'adminNotificationTemplate' => $template,
            'adminNotificationBody' => $body,
            'adminNotificationSubject' => $subject,
            'adminNotificationHistory' => $history,
        ];
    }

    private function updateUserStatus(): array
    {
        $adminId = (int) \auth_user()['id'];
        $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        if (!$userId || !in_array($status, ['active', 'inactive', 'suspended'], true)) {
            throw new RuntimeException('Select a valid account and status.');
        }
        if ((int) $userId === $adminId) {
            throw new RuntimeException('You cannot change your own account status.');
        }
        $target = \db()->prepare('SELECT role, status FROM tbl_users WHERE id=?');
        $target->execute([$userId]);
        $current = $target->fetch();
        if (!$current || $current['role'] === 'admin') {
            throw new RuntimeException('This account could not be changed. Administrator accounts are protected here.');
        }
        if ($current['status'] === $status) {
            return ['page' => 'admin_users', 'notice' => 'The account already has that status.'];
        }
        $stmt = \db()->prepare("UPDATE tbl_users SET status=? WHERE id=? AND role<>'admin' AND status=? AND status IN ('active','inactive','suspended')");
        $stmt->execute([$status, $userId, $current['status']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('This account could not be changed. Administrator accounts are protected here.');
        }
        \audit_log(
            $adminId,
            'account.status.changed',
            'user',
            (int) $userId,
            'success',
            'Account status changed from ' . $current['status'] . ' to ' . $status . '.'
        );
        return ['page' => 'admin_users', 'notice' => 'Account status updated.'];
    }

    private function sendNotifications(): array
    {
        $adminId = (int) \auth_user()['id'];
        $companyIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['company_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        $template = (string) ($_POST['template'] ?? '');
        $subject = trim(mb_substr((string) ($_POST['subject'] ?? ''), 0, 180));
        $message = trim((string) ($_POST['message'] ?? ''));
        if (!$companyIds || count($companyIds) > 100 || !isset(self::NOTIFICATION_TEMPLATES[$template]) || $subject === '' || $message === '' || mb_strlen($message) > 5000 || (string) ($_POST['confirm_send'] ?? '') !== '1') {
            throw new RuntimeException('Choose up to 100 companies and provide a valid notification subject and message.');
        }
        if (count($companyIds) > 1 && (string) ($_POST['confirm_bulk'] ?? '') !== '1') {
            throw new RuntimeException('Confirm the bulk notification before sending.');
        }

        $placeholder = implode(',', array_fill(0, count($companyIds), '?'));
        $recipients = \db()->prepare(
            "SELECT c.id AS company_id, c.name AS company_name, u.email
             FROM tbl_companies c JOIN tbl_company_members m ON m.company_id=c.id AND m.member_role='owner' AND m.status='active'
             JOIN tbl_users u ON u.id=m.user_id
             WHERE c.id IN ({$placeholder}) AND u.status='active' AND u.email_verified_at IS NOT NULL"
        );
        $recipients->execute($companyIds);
        $recipientRows = $recipients->fetchAll();
        if (count($recipientRows) !== count($companyIds)) {
            throw new RuntimeException('One or more selected companies have no active, verified owner email address.');
        }
        $emailService = new EmailService();
        if (!$emailService->configured()) {
            throw new RuntimeException('The configured email provider is unavailable. No company notification was sent.');
        }

        foreach ($recipientRows as $recipient) {
            $providerName = $emailService->providerName();
            try {
                $result = $emailService->sendMessage((string) $recipient['email'], $subject, $message);
                $this->recordEmailAttempt($adminId, $recipient, $template, $subject, $message, 'sent', $providerName, $result['http_status'], null);
                \audit_log($adminId, 'admin.notification.sent', 'company', (int) $recipient['company_id'], 'success', 'Administrative email notification sent to a company.');
            } catch (ProviderException $exception) {
                $this->recordEmailAttempt($adminId, $recipient, $template, $subject, $message, 'failed', $providerName, $exception->httpStatus, $exception->providerCode);
                $this->notifyAdminsOfFailure((int) $recipient['company_id'], (string) $recipient['company_name']);
                \audit_log($adminId, 'admin.notification.failed', 'company', (int) $recipient['company_id'], 'failure', 'Administrative email notification delivery failed.');
                throw new RuntimeException('Notification delivery failed for ' . $recipient['company_name'] . '. The attempt was recorded; review Notification History before retrying.');
            }
        }
        return ['page' => 'admin_notifications', 'notice' => 'Notification sent to ' . count($recipientRows) . ' company account(s).'];
    }

    private function recordEmailAttempt(
        int $adminId,
        array $recipient,
        string $template,
        string $subject,
        string $message,
        string $status,
        string $provider,
        ?int $httpStatus,
        ?string $errorCode
    ): void {
        \db()->prepare(
            'INSERT INTO tbl_admin_email_logs (company_id, company_name, recipient_email, template_key, subject, message_body, delivery_status, provider_name, http_status_code, error_code, sent_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $recipient['company_id'],
            (string) $recipient['company_name'],
            (string) $recipient['email'],
            $template,
            $subject,
            $message,
            $status,
            $provider,
            $httpStatus,
            $errorCode,
            $adminId,
        ]);
    }

    private function notifyAdminsOfFailure(int $companyId, string $companyName): void
    {
        \db()->prepare(
            "INSERT INTO tbl_notifications (user_id, title, message, related_type, related_id)
             SELECT id, 'Administrative email delivery failed', ?, 'company', ?
             FROM tbl_users WHERE role='admin' AND status='active'"
        )->execute(['The administrative notification to ' . $companyName . ' could not be delivered. Review Notification History before retrying.', $companyId]);
    }

    private function notificationBody(string $template, string $customMessage): string
    {
        $bodies = [
            'welcome' => 'Welcome to SMARTJOB. Your company account has been registered. Please complete your company profile and verification requirements.',
            'verification_pending' => 'Your company verification is pending review. We will notify you when the review is complete.',
            'verification_approved' => 'Your company verification has been approved. You may now access employer features available to verified companies.',
            'verification_rejected' => 'Your company verification was not approved. Please review the administrator notes and contact SMARTJOB support if you need assistance.',
            'missing_requirements' => 'Additional company verification requirements are needed. Sign in to SMARTJOB and review the requested documents on your Company Verification page.',
            'account_reminder' => 'This is an administrative reminder about your SMARTJOB company account. Please sign in to review your account information and any pending actions.',
            'custom' => trim($customMessage),
        ];
        return $bodies[$template] ?? '';
    }

    private function notificationSubject(string $template): string
    {
        return match ($template) {
            'welcome' => 'Welcome to SMARTJOB',
            'verification_pending' => 'SMARTJOB company verification pending',
            'verification_approved' => 'SMARTJOB company verification approved',
            'verification_rejected' => 'SMARTJOB company verification update',
            'missing_requirements' => 'Action required: SMARTJOB verification documents',
            'account_reminder' => 'SMARTJOB company account reminder',
            'custom' => '',
            default => '',
        };
    }

    private function pageNumber(): int
    {
        $page = filter_var($_GET['p'] ?? null, FILTER_VALIDATE_INT);
        return $page !== false && $page !== null ? max(1, min(100000, $page)) : 1;
    }
}
