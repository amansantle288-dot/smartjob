<?php
declare(strict_types=1);

function load_local_environment(): void
{
    $environmentFile = dirname(__DIR__) . '/.env';
    if (!is_file($environmentFile)) {
        return;
    }

    $contents = file_get_contents($environmentFile);
    if (!is_string($contents)) {
        throw new RuntimeException('The local environment configuration could not be read.');
    }

    foreach (preg_split('/\r\n|\n|\r/', $contents) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !preg_match('/\A([A-Z][A-Z0-9_]*)=(.*)\z/', $line, $matches)) {
            continue;
        }

        $name = $matches[1];
        if (getenv($name) !== false) {
            continue;
        }

        $value = trim($matches[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($name . '=' . $value);
    }
}

load_local_environment();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/Router.php';
require_once __DIR__ . '/services/MatchingService.php';
require_once __DIR__ . '/services/HiringCountdown.php';
require_once __DIR__ . '/services/AIWritingServiceInterface.php';
require_once __DIR__ . '/services/AIHttpClient.php';
require_once __DIR__ . '/services/OpenAIWritingService.php';
require_once __DIR__ . '/services/GeminiWritingService.php';
require_once __DIR__ . '/services/AIWritingServiceFactory.php';
require_once __DIR__ . '/services/CredentialStorage.php';
require_once __DIR__ . '/services/CompanyDocumentStorage.php';
require_once __DIR__ . '/services/EmailService.php';
require_once __DIR__ . '/Modules/Authentication/Controllers/AuthenticationController.php';
require_once __DIR__ . '/Modules/Authentication/Controllers/AccountSettingsController.php';
require_once __DIR__ . '/Modules/Admin/Controllers/AdminController.php';
require_once __DIR__ . '/Modules/Student/Controllers/StudentProfileController.php';
require_once __DIR__ . '/Modules/AI/Controllers/AIWritingController.php';
require_once __DIR__ . '/Modules/Company/Controllers/ExploreController.php';
require_once __DIR__ . '/services/ProfilePhotoStorage.php';
require_once __DIR__ . '/Modules/Credentials/Controllers/CredentialController.php';
require_once __DIR__ . '/Modules/Training/Controllers/TrainingController.php';
require_once __DIR__ . '/Modules/SmartMessage/Controllers/MessageController.php';
require_once __DIR__ . '/Modules/SmartMail/Controllers/EmailController.php';
require_once __DIR__ . '/Modules/Company/Controllers/CompanyController.php';
require_once __DIR__ . '/Modules/Internship/Controllers/InternshipController.php';
require_once __DIR__ . '/Modules/SavedInternships/Controllers/SavedInternshipController.php';
require_once __DIR__ . '/Modules/SavedCompanies/Controllers/SavedCompanyController.php';
require_once __DIR__ . '/Modules/Notifications/Controllers/NotificationController.php';
require_once __DIR__ . '/Modules/Placements/Controllers/PlacementController.php';
require_once __DIR__ . '/Modules/Applications/Controllers/ApplicationController.php';
require_once __DIR__ . '/Modules/SmartMail/Services/EmailProviderInterface.php';
require_once __DIR__ . '/Modules/SmartMail/Services/BrevoEmailProvider.php';
require_once __DIR__ . '/Modules/SmartMail/Services/MailjetEmailProvider.php';
require_once __DIR__ . '/Modules/SmartMail/Services/SendGridEmailProvider.php';
require_once __DIR__ . '/Modules/SmartMail/Services/SmtpEmailProvider.php';
require_once __DIR__ . '/Modules/SmartMail/Services/EmailService.php';
require_once __DIR__ . '/Modules/SmsGateway/Services/SmsProviderInterface.php';
require_once __DIR__ . '/Modules/SmsGateway/Services/TextBeeSmsProvider.php';
require_once __DIR__ . '/Modules/SmsGateway/Services/TwilioSmsProvider.php';
require_once __DIR__ . '/Modules/SmsGateway/Services/SmsService.php';
require_once __DIR__ . '/Modules/AccountVerification/Services/ProviderException.php';
require_once __DIR__ . '/Modules/AccountVerification/Services/PhoneNumber.php';
require_once __DIR__ . '/Modules/AccountVerification/Services/VerificationConfig.php';
require_once __DIR__ . '/Modules/AccountVerification/Models/AccountVerificationModel.php';
require_once __DIR__ . '/Modules/AccountVerification/Models/VerificationDeliveryModel.php';
require_once __DIR__ . '/Modules/AccountVerification/Services/ProviderAuditLogger.php';
require_once __DIR__ . '/Modules/AccountVerification/Services/AccountActivationService.php';
require_once __DIR__ . '/Modules/AccountVerification/Services/OtpService.php';
require_once __DIR__ . '/Modules/AccountVerification/Controllers/EmailVerificationController.php';
require_once __DIR__ . '/Modules/AccountVerification/Controllers/SmsVerificationController.php';
require_once __DIR__ . '/Modules/AccountVerification/Controllers/OtpVerificationController.php';
require_once __DIR__ . '/Modules/AccountVerification/Controllers/VerificationAdminController.php';
if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

function app_secret(): string
{
    $secret = getenv('SMARTJOB_APP_KEY');
    if (is_string($secret) && strlen($secret) >= 32) {
        return $secret;
    }
    if (getenv('APP_ENV') === 'production') {
        throw new RuntimeException('SMARTJOB_APP_KEY must be configured with at least 32 characters in production.');
    }
    return hash('sha256', 'SMARTJOB-local-development|' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('smartjob_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    return \SMARTJOB\Core\Database::connection();
}

function db_transaction(callable $operation): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $operation($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_validate(): void
{
    $submitted = (string) ($_POST['_csrf'] ?? '');
    if ($submitted === '' || !hash_equals(csrf_token(), $submitted)) {
        throw new RuntimeException('Your session token expired. Refresh the page and try again.');
    }
}

function auth_user(): ?array
{
    $currentUserId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$currentUserId) {
        return null;
    }
    static $loadedUserId = null;
    static $user = null;
    if ($loadedUserId !== $currentUserId) {
        $stmt = db()->prepare("SELECT id, full_name, email, phone_number, email_verified_at, phone_verified_at, verification_required, phone_verification_required, role, status FROM tbl_users WHERE id = ? AND status = 'active'");
        $stmt->execute([$currentUserId]);
        $user = $stmt->fetch() ?: null;
        if (!$user) {
            unset($_SESSION['user_id']);
            $loadedUserId = null;
        } else {
            $loadedUserId = $currentUserId;
        }
    }
    return $user;
}

function auth_login_by_id(int $userId): void
{
    session_regenerate_id(true);
    unset($_SESSION['_csrf']);
    $_SESSION['user_id'] = $userId;
}

function require_login(): void
{
    if (!auth_user()) {
        redirect('login');
    }
}

function require_role(string $role): void
{
    require_login();
    if ((auth_user()['role'] ?? '') !== $role) {
        http_response_code(403);
        throw new RuntimeException('You are not authorized to perform this action.');
    }
}

function redirect(string $page): never
{
    header('Location: index.php?page=' . rawurlencode($page), true, 303);
    exit;
}

function validate_account(string $name, string $email, string $password): void
{
    if ($name === '' || mb_strlen($name) > 120) {
        throw new RuntimeException('Enter a name between 1 and 120 characters.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        throw new RuntimeException('Enter a valid email address.');
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        throw new RuntimeException('Password must be between 12 and 72 bytes.');
    }
}

function audit_log(
    ?int $userId,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    string $result = 'success',
    ?string $description = null
): void
{
    if (!in_array($result, ['success', 'failure'], true)) {
        throw new RuntimeException('Invalid audit event result.');
    }
    $actor = auth_user();
    $actorRole = $actor && (int) $actor['id'] === $userId ? (string) $actor['role'] : null;
    if ($actorRole === null && $userId !== null) {
        $roleQuery = db()->prepare('SELECT role FROM tbl_users WHERE id=?');
        $roleQuery->execute([$userId]);
        $actorRoleValue = $roleQuery->fetchColumn();
        $actorRole = is_string($actorRoleValue) ? $actorRoleValue : null;
    }
    $safeDescription = $description ?? 'Recorded event: ' . str_replace(['.', '_'], [' ', ' '], $action);
    db()->prepare('INSERT INTO tbl_audit_logs (user_id, actor_role, action, entity_type, entity_id, result, description, ip_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $actorRole, $action, $entityType, $entityId, $result, mb_substr($safeDescription, 0, 500), hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), app_secret())]);
}

function current_company_id(int $userId): int
{
    $stmt = db()->prepare("SELECT company_id FROM tbl_company_members WHERE user_id = ? AND status = 'active' ORDER BY id LIMIT 1");
    $stmt->execute([$userId]);
    $companyId = $stmt->fetchColumn();
    if (!$companyId) {
        throw new RuntimeException('Your account is not associated with an active company.');
    }
    return (int) $companyId;
}

function require_approved_company(int $userId, string $action = ''): int
{
    require_role('employer');
    $companyId = current_company_id($userId);
    $stmt = db()->prepare("SELECT verification_status FROM tbl_companies WHERE id=?");
    $stmt->execute([$companyId]);
    if ($stmt->fetchColumn() !== 'approved') {
        if ($action !== '') {
            throw new RuntimeException("Your company must be approved before {$action} internships.");
        }
        throw new RuntimeException('Employer access is restricted until your company is verified.');
    }

    return $companyId;
}

function render_page(string $page, ?string $notice, array $errors, array $viewData = []): void
{
    extract($viewData, EXTR_SKIP);
    $user = auth_user();
    $roleAccess = [
        'dashboard' => ['student', 'employer', 'coordinator', 'admin'],
        'profile' => ['student'],
        'biodata' => ['student'],
        'account_settings' => ['student', 'employer', 'coordinator', 'admin'],
        'credentials' => ['student'],
        'training' => ['student', 'coordinator'],
        'messages' => ['student', 'employer', 'coordinator', 'admin'],
        'email' => ['student', 'employer', 'coordinator', 'admin'],
        'applications' => ['student', 'employer'],
        'saved' => ['student'],
        'saved_companies' => ['student'],
        'notifications' => ['student', 'employer', 'coordinator', 'admin'],
        'placements' => ['coordinator'],
        'applicant_biodata' => ['employer'],
        'companies' => ['admin'],
        'admin_companies' => ['admin'],
        'admin_users' => ['admin'],
        'admin_audit' => ['admin'],
        'admin_notifications' => ['admin'],
        'company_verification' => ['employer'],
        'company_profile' => ['employer'],
        'verification_admin' => ['admin'],
        'internships' => ['student', 'employer', 'admin'],
    ];
    if (isset($roleAccess[$page])) {
        if (!$user && $page !== 'internships') {
            redirect('login');
        }
        if ($user && !in_array($user['role'], $roleAccess[$page], true)) {
            http_response_code(403);
            $page = 'forbidden';
        }
    }
    if ($user && $user['role'] === 'employer' && in_array($page, ['applications', 'messages', 'email'], true)) {
        $companyId = current_company_id((int) $user['id']);
        $companyStatus = db()->prepare('SELECT verification_status FROM tbl_companies WHERE id=?');
        $companyStatus->execute([$companyId]);
        if ($companyStatus->fetchColumn() !== 'approved') {
            $page = 'company_verification';
        }
    }
    if ($page === 'explore' && !isset($viewData['companies'])) {
        $viewData = array_merge($viewData, (new \SMARTJOB\Modules\Company\Controllers\ExploreController())->viewData('explore'));
        extract($viewData, EXTR_SKIP);
    }
    require __DIR__ . '/views/page.php';
}

function count_query(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function load_biodata(int $userId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT u.full_name, u.email, u.phone_number, sp.program, sp.year_level, sp.profile_photo_key, b.date_of_birth, b.phone, b.address, b.city, b.province, b.summary, b.template, b.visibility FROM tbl_users u LEFT JOIN tbl_student_profiles sp ON sp.user_id=u.id LEFT JOIN tbl_student_biodata b ON b.user_id=u.id WHERE u.id=? AND u.role=?');
    $stmt->execute([$userId, 'student']);
    $student = $stmt->fetch();
    if (!$student) {
        throw new RuntimeException('Student biodata was not found.');
    }
    $student['phone'] = $student['phone_number'] ?: ($student['phone'] ?? null);
    $student['photo_data_uri'] = \ProfilePhotoStorage::dataUri($student['profile_photo_key'] ?? null);
    $student['visibility'] = json_decode((string) ($student['visibility'] ?? '["education","experience","projects","skills","training","certifications"]'), true) ?: [];
    $attainment = $pdo->prepare('SELECT * FROM tbl_student_educational_attainment WHERE user_id=?');
    $attainment->execute([$userId]);
    $student['education_background'] = $attainment->fetch() ?: [];
    foreach ([
        'education' => "SELECT id, education_level, school_name, location, program, department, major, year_level, achievements, start_date, end_date, expected_graduation_year, academic_status, is_current FROM tbl_student_education WHERE user_id=? AND education_level='tertiary' ORDER BY COALESCE(start_date, end_date) ASC, id ASC",
        'experience' => 'SELECT id, title, organization, description, start_date, end_date FROM tbl_student_experiences WHERE user_id=? ORDER BY COALESCE(end_date, start_date) DESC, id DESC',
        'projects' => 'SELECT id, title, role, description, technologies, project_url, start_date, end_date FROM tbl_student_projects WHERE user_id=? ORDER BY id DESC',
        'skills' => 'SELECT s.name, ss.proficiency, ss.description FROM tbl_student_skills ss JOIN tbl_skills s ON s.id=ss.skill_id WHERE ss.student_id=? ORDER BY s.name',
        'training' => "SELECT title, provider, description, completion_date, duration_hours, acquired_skills, verification_status FROM tbl_student_trainings WHERE user_id=? ORDER BY completion_date DESC, id DESC",
        'certifications' => 'SELECT title, issuer, issued_at, expires_at, verification_status FROM tbl_student_certifications WHERE user_id=? ORDER BY issued_at DESC, id DESC',
    ] as $key => $sql) {
        $query = $pdo->prepare($sql);
        $query->execute([$userId]);
        $student[$key] = $query->fetchAll();
    }
    return $student;
}

function application_counterpart_email(int $userId, int $applicationId): string
{
    $user = auth_user();
    if (!$user || (int) $user['id'] !== $userId) {
        throw new RuntimeException('Email sender session is invalid.');
    }
    $stmt = db()->prepare('SELECT a.student_id, i.company_id FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id WHERE a.id=?');
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch();
    if (!$application) {
        throw new RuntimeException('Application not found.');
    }
    if ($user['role'] === 'student' && (int) $application['student_id'] === $userId) {
        $targetSql = "SELECT u.email FROM tbl_company_members cm JOIN tbl_users u ON u.id=cm.user_id WHERE cm.company_id=? AND cm.status='active' ORDER BY FIELD(cm.member_role,'owner','manager','reviewer'), cm.id LIMIT 1";
        $targetId = (int) $application['company_id'];
    } elseif ($user['role'] === 'employer' && current_company_id($userId) === (int) $application['company_id']) {
        $targetSql = 'SELECT email FROM tbl_users WHERE id=?';
        $targetId = (int) $application['student_id'];
    } elseif ($user['role'] === 'coordinator') {
        $authorized = db()->prepare('SELECT COUNT(*) FROM tbl_ojt_coordinators c JOIN tbl_student_profiles s ON s.user_id=? WHERE c.user_id=? AND c.institution_id=s.institution_id AND c.status=?');
        $authorized->execute([(int) $application['student_id'], $userId, 'active']);
        if (!(int) $authorized->fetchColumn()) {
            throw new RuntimeException('This student is outside your assigned institution.');
        }
        $targetSql = 'SELECT email FROM tbl_users WHERE id=?';
        $targetId = (int) $application['student_id'];
    } elseif ($user['role'] === 'admin') {
        $targetSql = "SELECT u.email FROM tbl_company_members cm JOIN tbl_users u ON u.id=cm.user_id WHERE cm.company_id=? AND cm.status='active' ORDER BY FIELD(cm.member_role,'owner','manager','reviewer'), cm.id LIMIT 1";
        $targetId = (int) $application['company_id'];
    } else {
        throw new RuntimeException('You are not authorized to email a participant in this application.');
    }
    $target = db()->prepare($targetSql);
    $target->execute([$targetId]);
    $email = $target->fetchColumn();
    if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The other application participant has no valid email address.');
    }
    return $email;
}

function open_application_conversation(int $userId, int $applicationId, string $subject, string $body): int
{
    $currentUser = auth_user();
    $stmt = db()->prepare('SELECT a.student_id, i.company_id, sp.institution_id FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id LEFT JOIN tbl_student_profiles sp ON sp.user_id=a.student_id WHERE a.id=?');
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch();
    if (!$application || !$currentUser || (int) $currentUser['id'] !== $userId) {
        throw new RuntimeException('Application or sender session not found.');
    }
    if ($currentUser['role'] === 'student' && (int) $application['student_id'] === $userId) {
        $target = db()->prepare("SELECT user_id FROM tbl_company_members WHERE company_id=? AND status='active' ORDER BY FIELD(member_role,'owner','manager','reviewer'), id LIMIT 1");
        $target->execute([(int) $application['company_id']]);
        $targetUserId = (int) $target->fetchColumn();
    } elseif ($currentUser['role'] === 'employer' && current_company_id($userId) === (int) $application['company_id']) {
        $targetUserId = (int) $application['student_id'];
    } elseif ($currentUser['role'] === 'coordinator') {
        $authorized = db()->prepare('SELECT COUNT(*) FROM tbl_ojt_coordinators WHERE user_id=? AND institution_id=? AND status=?');
        $authorized->execute([$userId, $application['institution_id'], 'active']);
        if (!(int) $authorized->fetchColumn()) {
            throw new RuntimeException('This applicant is outside your assigned institution.');
        }
        $targetUserId = (int) $application['student_id'];
    } elseif ($currentUser['role'] === 'admin') {
        $target = db()->prepare("SELECT user_id FROM tbl_company_members WHERE company_id=? AND status='active' ORDER BY FIELD(member_role,'owner','manager','reviewer'), id LIMIT 1");
        $target->execute([(int) $application['company_id']]);
        $targetUserId = (int) $target->fetchColumn();
    } else {
        throw new RuntimeException('You are not authorized to start a conversation about this application.');
    }
    if (!$targetUserId) {
        throw new RuntimeException('The other participant has no active account.');
    }
    return db_transaction(function (PDO $pdo) use ($userId, $applicationId, $targetUserId, $subject, $body): int {
        $find = $pdo->prepare('SELECT c.id FROM tbl_conversations c JOIN tbl_conversation_participants mine ON mine.conversation_id=c.id AND mine.user_id=? JOIN tbl_conversation_participants theirs ON theirs.conversation_id=c.id AND theirs.user_id=? WHERE c.application_id=? ORDER BY c.id DESC LIMIT 1');
        $find->execute([$userId, $targetUserId, $applicationId]);
        $conversationId = (int) $find->fetchColumn();
        if (!$conversationId) {
            $pdo->prepare('INSERT INTO tbl_conversations (application_id, subject, created_by) VALUES (?, ?, ?)')->execute([$applicationId, $subject, $userId]);
            $conversationId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO tbl_conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)')->execute([$conversationId, $userId, $conversationId, $targetUserId]);
        }
        $pdo->prepare('INSERT INTO tbl_messages (conversation_id, sender_id, body) VALUES (?, ?, ?)')->execute([$conversationId, $userId, $body]);
        $pdo->prepare('UPDATE tbl_conversations SET updated_at=NOW() WHERE id=?')->execute([$conversationId]);
        $pdo->prepare('UPDATE tbl_conversation_participants SET last_read_at=NOW() WHERE conversation_id=? AND user_id=?')->execute([$conversationId, $userId]);
        $pdo->prepare("INSERT INTO tbl_notifications (user_id, title, message) VALUES (?, 'New SMARTMESSAGE', ?)")->execute([$targetUserId, mb_substr($body, 0, 250)]);
        $pdo->prepare("INSERT INTO tbl_audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'message.conversation_started', 'conversation', ?)")->execute([$userId, $conversationId]);
        return $conversationId;
    });
}
