<?php
declare(strict_types=1);

namespace SMARTJOB\Core;

use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Throwable;

final class Router
{
    private const POST_ACTIONS = [
        'setup_admin' => \SMARTJOB\Modules\Authentication\Controllers\AuthenticationController::class,
        'register' => \SMARTJOB\Modules\Authentication\Controllers\AuthenticationController::class,
        'login' => \SMARTJOB\Modules\Authentication\Controllers\AuthenticationController::class,
        'logout' => \SMARTJOB\Modules\Authentication\Controllers\AuthenticationController::class,
        'save_profile' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'save_education_background' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'upload_profile_photo' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'remove_profile_photo' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'save_biodata' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'add_education' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'update_education' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'delete_education' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'add_project' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'add_experience' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'add_skill' => \SMARTJOB\Modules\Student\Controllers\StudentProfileController::class,
        'ai_write' => \SMARTJOB\Modules\AI\Controllers\AIWritingController::class,
        'update_account_contacts' => \SMARTJOB\Modules\Authentication\Controllers\AccountSettingsController::class,
        'update_account_password' => \SMARTJOB\Modules\Authentication\Controllers\AccountSettingsController::class,
        'upload_credential' => \SMARTJOB\Modules\Credentials\Controllers\CredentialController::class,
        'delete_credential' => \SMARTJOB\Modules\Credentials\Controllers\CredentialController::class,
        'add_training' => \SMARTJOB\Modules\Training\Controllers\TrainingController::class,
        'verify_training' => \SMARTJOB\Modules\Training\Controllers\TrainingController::class,
        'add_certification' => \SMARTJOB\Modules\Training\Controllers\TrainingController::class,
        'create_conversation' => \SMARTJOB\Modules\SmartMessage\Controllers\MessageController::class,
        'send_message' => \SMARTJOB\Modules\SmartMessage\Controllers\MessageController::class,
        'report_message' => \SMARTJOB\Modules\SmartMessage\Controllers\MessageController::class,
        'save_email_draft' => \SMARTJOB\Modules\SmartMail\Controllers\EmailController::class,
        'send_email' => \SMARTJOB\Modules\SmartMail\Controllers\EmailController::class,
        'review_company' => \SMARTJOB\Modules\Company\Controllers\CompanyController::class,
        'review_company_document' => \SMARTJOB\Modules\Company\Controllers\CompanyController::class,
        'upload_company_document' => \SMARTJOB\Modules\Company\Controllers\CompanyController::class,
        'upload_company_documents' => \SMARTJOB\Modules\Company\Controllers\CompanyController::class,
        'save_company_profile' => \SMARTJOB\Modules\Company\Controllers\CompanyController::class,
        'save_company_requirements' => \SMARTJOB\Modules\Company\Controllers\CompanyController::class,
        'create_internship' => \SMARTJOB\Modules\Internship\Controllers\InternshipController::class,
        'update_internship' => \SMARTJOB\Modules\Internship\Controllers\InternshipController::class,
        'delete_internship' => \SMARTJOB\Modules\Internship\Controllers\InternshipController::class,
        'save_internship' => \SMARTJOB\Modules\SavedInternships\Controllers\SavedInternshipController::class,
        'unsave_internship' => \SMARTJOB\Modules\SavedInternships\Controllers\SavedInternshipController::class,
        'save_company' => \SMARTJOB\Modules\SavedCompanies\Controllers\SavedCompanyController::class,
        'unsave_company' => \SMARTJOB\Modules\SavedCompanies\Controllers\SavedCompanyController::class,
        'mark_notification_read' => \SMARTJOB\Modules\Notifications\Controllers\NotificationController::class,
        'mark_all_notifications_read' => \SMARTJOB\Modules\Notifications\Controllers\NotificationController::class,
        'admin_update_user_status' => \SMARTJOB\Modules\Admin\Controllers\AdminController::class,
        'admin_send_notifications' => \SMARTJOB\Modules\Admin\Controllers\AdminController::class,
        'approve_placement' => \SMARTJOB\Modules\Placements\Controllers\PlacementController::class,
        'apply' => \SMARTJOB\Modules\Applications\Controllers\ApplicationController::class,
        'update_biodata_sharing' => \SMARTJOB\Modules\Applications\Controllers\ApplicationController::class,
        'review_qualifications' => \SMARTJOB\Modules\Applications\Controllers\ApplicationController::class,
        'update_application' => \SMARTJOB\Modules\Applications\Controllers\ApplicationController::class,
        'withdraw_application' => \SMARTJOB\Modules\Applications\Controllers\ApplicationController::class,
    ];

    public function dispatch(): void
    {
        $page = (string) ($_GET['page'] ?? 'home');
        $errors = [];
        $notice = null;
        $viewData = [];

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $this->dispatchGet($page);
                if ($page === 'notification_feed') {
                    (new \SMARTJOB\Modules\Notifications\Controllers\NotificationController())->feed();
                }
                if ($page === 'message_summary') {
                    (new \SMARTJOB\Modules\SmartMessage\Controllers\MessageController())->summary();
                }
                if (in_array($page, ['home', 'explore'], true)) {
                    $viewData = (new \SMARTJOB\Modules\Company\Controllers\ExploreController())->viewData($page);
                }
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                csrf_validate();
                $action = (string) ($_POST['action'] ?? '');
                $verificationRoutes = require dirname(__DIR__, 2) . '/routes/verification.php';
                $controllerClass = (self::POST_ACTIONS + $verificationRoutes['post'])[$action] ?? null;
                if ($controllerClass === null) {
                    throw new RuntimeException('Unknown form action.');
                }

                $result = (new $controllerClass())->handle($action);
                $page = $result['page'];
                $notice = $result['notice'];
                $viewData = $result['viewData'] ?? [];
                if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
                    \SMARTJOB\Modules\Notifications\Controllers\NotificationController::json(['ok' => true, 'notice' => $notice] + ($result['json'] ?? []));
                }
            }

            if (in_array($page, ['dashboard', 'account_settings'], true)) {
                require_login();
            }
            if (in_array($page, ['dashboard', 'admin_companies', 'admin_users', 'admin_audit', 'admin_notifications'], true) && (auth_user()['role'] ?? '') === 'admin') {
                $viewData = (new \SMARTJOB\Modules\Admin\Controllers\AdminController())->viewData($page);
            }
            if ($page === 'verification_admin') {
                $viewData = (new \SMARTJOB\Modules\AccountVerification\Controllers\VerificationAdminController())->viewData();
            }
            render_page($page, $notice, $errors, $viewData);
        } catch (Throwable $exception) {
            if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
                $status = $exception instanceof RuntimeException ? 400 : 500;
                if ($status === 500) {
                    error_log((string) $exception);
                }
                \SMARTJOB\Modules\Notifications\Controllers\NotificationController::json(['ok' => false, 'error' => $exception instanceof RuntimeException ? $exception->getMessage() : 'The request could not be completed.'], $status);
            }
            if ($page === 'applicant_biodata') {
                $page = 'applications';
            }
            if ($exception instanceof PDOException && str_contains((string) $exception->getCode(), '23000')) {
                $errors[] = 'A duplicate or related-record constraint prevented this update.';
            } elseif ($exception instanceof RuntimeException || $exception instanceof InvalidArgumentException) {
                $errors[] = $exception->getMessage();
            } else {
                error_log((string) $exception);
                $errors[] = 'The request could not be completed. Please check the application logs or try again.';
            }
            if ($page === 'verification_admin') {
                $viewData = (new \SMARTJOB\Modules\AccountVerification\Controllers\VerificationAdminController())->viewData();
            }
            if (in_array($page, ['dashboard', 'admin_companies', 'admin_users', 'admin_audit', 'admin_notifications'], true) && (auth_user()['role'] ?? '') === 'admin') {
                $viewData = (new \SMARTJOB\Modules\Admin\Controllers\AdminController())->viewData($page);
            }
            render_page($page, $notice, $errors, $viewData);
        }
    }

    private function dispatchGet(string $page): void
    {
        $handled = false;

        switch ($page) {
            case 'applicant_biodata':
                (new \SMARTJOB\Modules\Applications\Controllers\ApplicationController())->showSharedBiodata();
                $handled = true;
                break;
            case 'application_document':
                (new \SMARTJOB\Modules\Applications\Controllers\ApplicationController())->downloadSnapshot();
                $handled = true;
                break;
            case 'credential_download':
                (new \SMARTJOB\Modules\Credentials\Controllers\CredentialController())->download();
                $handled = true;
                break;
            case 'message_poll':
                (new \SMARTJOB\Modules\SmartMessage\Controllers\MessageController())->poll();
                $handled = true;
                break;
            case 'biodata_pdf':
                (new \SMARTJOB\Modules\Student\Controllers\StudentProfileController())->exportPdf();
                $handled = true;
                break;
            case 'profile_photo':
                (new \SMARTJOB\Modules\Student\Controllers\StudentProfileController())->streamProfilePhoto();
                $handled = true;
                break;
            case 'company_logo':
                (new \SMARTJOB\Modules\Company\Controllers\CompanyController())->streamLogo();
                $handled = true;
                break;
            case 'company_document':
                (new \SMARTJOB\Modules\Company\Controllers\CompanyController())->streamDocument();
                $handled = true;
                break;
            case 'admin_audit_export':
                (new \SMARTJOB\Modules\Admin\Controllers\AdminController())->exportAuditCsv();
                $handled = true;
                break;
        }

        if ($handled) {
            return;
        }

        $verificationRoutes = require dirname(__DIR__, 2) . '/routes/verification.php';
        if (isset($verificationRoutes['get'][$page])) {
            (new $verificationRoutes['get'][$page]())->show();
        }
    }
}
