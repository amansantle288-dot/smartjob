# SMARTJOB

SMARTJOB is a PHP 8.2+/MySQL OJT placement and internship management application designed for XAMPP.

## Architecture

- **Entry point and routing:** `index.php` loads `app/bootstrap.php` and dispatches the request through `app/Core/Router.php`. The router applies CSRF validation and forwards GET endpoints and POST actions to module controllers while preserving the existing URLs.
- **Core and services:** `app/Core/Database.php` owns the reusable PDO connection. `app/bootstrap.php` exposes shared session/authentication helpers, CSRF protection, output escaping, authorization guards, and audit logging. `app/services/` contains domain logic such as the explainable competency matching scorer.
- **Presentation:** `app/views/page.php` renders role-aware pages; `assets/css/app.css` contains the responsive interface.
- **Persistence:** `database/smartjob_db.sql` defines the normalized MySQL schema, foreign keys, indexes, and initial matching configuration.

### Core relationships

`tbl_users` is the identity/role table. A student has a one-to-one `tbl_student_profiles` record. Employer users are linked to an organization through `tbl_company_members`; each company has a verification record and audit trail. A company owns internship opportunities. Opportunities collect student applications, each with status history. Applications may later create placements, which record progress. Student and opportunity skills connect through normalized skill junction tables; match results and recommendations preserve explainable score data.

### Module dependencies

Authentication and RBAC underpin all roles. Company review gates internship publishing. Internship opportunities precede applications; accepted applications precede placements. Student/internship skill profiles and published opportunities are prerequisites for persisted SMARTMATCH recommendations. Institution and coordinator authorization gate institutional monitoring and OJT partner approval.

### Implementation sequence

1. **Foundation — implemented:** project bootstrap, MySQL schema, account registration and authentication, role guards, shared responsive layout.
2. **Profiles — improved:** student academic details, preferences, complete educational attainment, private profile photos, and e-Biodata are editable; resumes remain in the private credential repository.
3. **SMARTVERIFY — partial:** administrators review private company documents individually, can approve or return them with feedback, and can complete final account verification only after current submitted documents are approved. Configurable policy-specific requirements and suspension administration remain.
4. **Internships — improved:** verified employers can publish, edit, and close categorized opportunities; students can search approved-company listings and save internships to their account. Capacity administration and persisted matching remain.
5. **SMARTMATCH — foundation only:** normalized skill tables, a standalone explainable skill-overlap scorer, and profile-informed company suggestions exist; persistence, configurable weighted matching, and eligibility remain.
6. **Applications and placements — partial:** application submission and employer review/status transitions work; notifications, coordinator decisions, placement creation, and progress tracking remain.
7. **Notifications and reports — schema only:** supporting tables exist; user-facing features and exports remain.
8. **Testing and deployment — partial:** PHP syntax, matching unit tests, selected MariaDB integration tests, and a local page preview are checked; comprehensive security testing and deployment preparation remain.

## Implemented in this increment

- Student/employer registration, one-time initial administrator setup, login/logout, password hashing, CSRF protection, session ID regeneration, role checks, login attempt throttling, and audit logging.
- Student academic profile basics and an editable e-Biodata with education, skills synchronized to the skill profile, project/work experience, optional section visibility, two layouts, browser print, and Dompdf PDF export.
- Private credential upload, ownership-protected downloads/deletion, file MIME and size validation, and explicit per-application document sharing. Storage defaults to `C:\xampp\private\smartjob`, outside the public web root.
- Student training/certification records with truthful self-reported/pending/verified statuses. Institution-assigned coordinators can review evidenced training and only verified training skills are included by the SMARTMATCH scoring service.
- SMARTMESSAGE application-based conversations with membership-checked access, unread counts, search, polling, reporting, rate limits, notifications, and audit records.
- SMARTMAIL application-related drafts with seeded templates and optional PHPMailer SMTP delivery. An absent provider leaves drafts unchanged; an SMTP accepted response is recorded as **sent**, not delivered.
- Company registration, private company verification document uploads, individual review/history, correction resubmission, and a separate final administrator approval step.
- Public and student Explore company directory backed by the same approved company and opportunity records, with name, industry, location, category, and skill filters, company profiles, public requirements, and student-only applications.
- Employer-editable public company profile and logo; internship categories, work modes, and requirements.
- Reauthenticated student contact settings, account-password changes for every authenticated role, Philippine mobile validation, and verification through the existing OTP workflow when required.
- Complete editable primary/secondary/tertiary educational attainment and profile photo upload, private storage, replacement/removal, and square 2×2-inch e-Biodata photo output.
- Responsive role-based navigation with a compact mobile menu and a shared account dropdown featuring profile identity, verified status, grouped role-aware destinations, keyboard controls, and a CSRF-protected logout action.
- Employer application status review, with application ownership enforced server-side.
- Employer qualification reviews are recorded against each application; employers must review a shared e-Biodata or resume and mark the applicant qualified before acceptance. Status transitions notify the applicant.
- Pending employers are restricted from reviewing applications, viewing shared applicant documents, using application messaging, or managing published opportunities.
- Account dropdown destinations follow the existing role and company-verification permissions. Generic profile pages for non-students, user management, reports, and audit-log pages are not implemented and are intentionally omitted.
- Employer internship editing and closing, with ownership checks and preserved application history.
- Company review notes and revision requests, with employer-visible feedback and profile resubmission.
- Account-backed student saved internships with an explicit migration and saved-list page.
- Account-backed interested companies with verified-only save controls and a dedicated list.
- Normalized database foundation and a reusable skill-overlap scoring service.

Authentication actions (initial administrator setup, registration, login, and logout), protected GET endpoints, and module form actions are routed to their module controllers; the root entry script now only bootstraps and dispatches.

Student profile actions for academic details, e-Biodata, education, projects, experience, and skills are handled by `app/Modules/Student/Controllers/StudentProfileController.php`.
Credential upload/deletion and training, certification, and coordinator verification actions are separated into their respective `app/Modules/Credentials/Controllers/` and `app/Modules/Training/Controllers/` controllers.
SMARTMESSAGE conversation actions and polling, plus SMARTMAIL draft and send actions, are handled in their corresponding module controllers.
Company review, internship publishing, and application submission/status/consent actions are handled in module-specific controllers as well.

### Public Explore and profile enhancement migration

For an existing installation, import `database/migrations/add_intern_explore_and_profile.sql` after `database/smartjob_db.sql` and `database/002_student_communication.sql`. It adds company logo and internship category/requirements fields, a private profile-photo reference, and a one-to-one educational attainment table. The migration is additive and preserves existing users, companies, opportunities, applications, and education records. Profile photos and company logos use the configured private storage root (default `C:\xampp\private\smartjob\profile-photos`); company logo requests are served only for approved companies. Employer logo/profile editing and newly added opportunity fields are available after the migration is applied.

### Saved internships migration

For an existing installation, import `database/migrations/create_saved_internships.sql` into `smartjob_db`. It creates an account-owned junction table with cascading foreign keys, so students' saved opportunities persist across devices and are removed if the student or opportunity is deleted. The saved-list page also retains closed or expired listings as unavailable until the student removes them.

For interested companies, import `database/migrations/create_saved_companies.sql`. Only approved companies can be saved; the list shows open opportunity counts and lets students remove companies without sharing their profile.

### Notification center

For an existing installation, import `database/migrations/add_notification_links.sql` into `smartjob_db`. It only adds optional relationship metadata to existing notifications, preserving all rows. Authenticated users get a recipient-scoped notification feed, read/unread controls, and a conversation preview with unread message counts in the navigation.

### Application document snapshots

For an existing installation, import `database/migrations/create_application_document_snapshots.sql` into `smartjob_db`. New application submissions copy each selected credential into private storage and record its SHA-256 digest so later edits to the student's original file do not change the document shared with that application. Existing application records remain intact and continue to use their original credential references. Resume uploads support PDF and DOCX (DOCX validation requires PHP ZIP support).

### Company verification requirements

For an existing installation, import `database/migrations/add_company_verification_requirements.sql` into `smartjob_db`. It adds an organization-type field and an administrator-configurable requirement matrix without removing verification records. Administrators select which supported document types are mandatory for each organization type; final approval requires all configured documents and every submitted current document to be approved. No required-document policy is assumed until an administrator configures one.

### Bulk company verification documents

For an existing installation, import `database/migrations/add_bulk_company_document_uploads.sql` after the company verification requirements and company document review migrations. It adds applicability settings (`required`, `optional`, or `not_applicable`) plus batch and version metadata without changing existing document decisions or history. New document requirements are grouped into business registration, representative, OJT partnership, and supporting-document sections. All four business registration requirements (DTI/SEC/CDA registration, business permit, BIR registration, and other business registration) are mandatory for every company type and cannot be changed to optional. Admins continue to review each file separately; every current attachment must be approved and every mandatory requirement must have an approved current attachment before final company verification.

Employers can select files from multiple requirements and submit them as one atomic batch, or resubmit several returned documents together. Files are stored individually in private storage; prior versions remain available to the owning company and administrators. New company verification uploads accept PDF, DOCX, JPG/JPEG, and PNG, with content checks and random storage names. Set `SMARTJOB_COMPANY_DOCUMENT_MAX_FILE_BYTES` and `SMARTJOB_COMPANY_DOCUMENT_MAX_BATCH_BYTES` in the Apache/PHP environment to change the default 8 MiB per-file and 32 MiB per-batch limits. Configure PHP `upload_max_filesize` and `post_max_size` high enough for the chosen limits; DOCX validation requires PHP ZIP support.

### Interview scheduling and placement approval

For an existing installation, import `database/migrations/add_application_interview_status.sql` into `smartjob_db`. It expands application history with interview scheduling details and preserves the existing status values. Employers can schedule or reschedule a future interview; students can withdraw applications before acceptance. When an accepted student's profile lists an institution with an active OJT coordinator, the application creates a pending placement for that institution. The coordinator's Placements workspace approves it and activates the placement with dates and required hours.

### Application and company document review migrations

For an existing installation, import `database/migrations/add_company_document_reviews.sql` and `database/migrations/add_application_qualification_reviews.sql` into `smartjob_db`. Company documents remain in the private storage directory; the database migration preserves prior rows and maps their legacy accepted/rejected review values to approved/returned-for-revision. Administrators must review each current submitted document and then complete the separate company verification decision. Applicants are notified when an employer changes an application status.

### Account contact verification

New student and employer accounts are created as `pending_verification` and cannot authenticate until required contacts are verified. Email verification is required by default; phone verification is optional unless `PHONE_VERIFICATION_REQUIRED=true`. A successful contact OTP activates the user account only when all required checks are complete. This does not approve the employer's company: SMARTVERIFY administrator review remains independent.

Verification persists only a keyed HMAC of each six-digit code. Codes expire, have limited attempts, are single-use, and are rate-limited for resend. Delivery records contain masked recipients and sanitized provider status only. Mailjet and TextBee are the defaults; Brevo, SendGrid, SMTP, and Twilio are selectable alternatives. Provider credentials stay in the server environment; the selected email and SMS provider and verification policy are stored in database settings. The administrator verification page shows configuration status and sanitized diagnostics. Its provider test actions require exact server-configured allowlist recipients.

For an existing database, import `database/migrations/create_account_verifications.sql`, `database/migrations/create_verification_delivery_logs.sql`, and `database/migrations/create_api_provider_logs.sql` in phpMyAdmin after the existing schema and communication migration. These additive migrations preserve the existing `tbl_users.id` key and data; `status` is extended to include `pending_verification` rather than adding a duplicate account-status field. New verification tables reference `tbl_users(id)`.

Configure `.env.example` values in the Apache/PHP process environment. Set `MAIL_PROVIDER=mailjet` with both `MAILJET_API_KEY` and `MAILJET_SECRET_KEY`, or select Brevo with `BREVO_API_KEY`, SendGrid with `SENDGRID_API_KEY`, or SMTP with its host and credentials. All options require a verified `MAIL_FROM_ADDRESS`. For TextBee, install and pair an authorized Android gateway, then set `TEXTBEE_API_KEY` and optionally `TEXTBEE_DEVICE_ID`; alternatively configure Twilio credentials and sender number. Set `VERIFICATION_TEST_EMAIL` and `VERIFICATION_TEST_PHONE` only to recipients administrators are authorized to test. No real provider credentials are committed. Integrations follow the official [Mailjet Send API](https://dev.mailjet.com/email/reference/send-emails/), [Brevo transactional email API](https://developers.brevo.com/reference/send-transac-email), [TextBee send SMS guide](https://textbee.dev/docs/sending-sms/sending-sms), and [Twilio Message API](https://www.twilio.com/docs/messaging/api/message-resource). A provider's accepted/submitted response is not proof of final delivery; live email/SMS delivery must be verified with authorized provider accounts and recipients. SMS is reported as configured based on server settings, not as operational until a real authorized send and delivery check succeeds.

### AI writing assistant

For an existing database, import `database/migrations/add_ai_writing_support.sql` into `smartjob_db`. It adds optional company mission/vision, skill descriptions, application letters, and per-user daily usage tracking without removing existing profile or application data. The student/employer writing assistant sends only the selected draft and explicitly selected context to the configured AI provider after consent. Drafts are previewed and are not saved unless the user accepts them and submits the existing form. Set `AI_PROVIDER=gemini` and configure `GEMINI_API_KEY` in the Apache/PHP process environment or private project-root `.env` file. Gemini is the default when configured; `GEMINI_MODEL` defaults to `gemini-3.5-flash-lite` and `GEMINI_TIMEOUT` defaults to 30 seconds. Set `AI_PROVIDER=openai` with `OPENAI_API_KEY` to use the optional OpenAI provider. When `AI_PROVIDER` is unset, the factory selects configured Gemini first, then configured OpenAI for existing installations. `.env` is loaded without overriding already-set process variables and is denied over HTTP. Never commit `.env` or paste API keys into source control or chat. `AI_STUDENT_DAILY_LIMIT` and `AI_EMPLOYER_DAILY_LIMIT` control the shared daily usage limits (10 and 20 by default); the existing `OPENAI_STUDENT_DAILY_LIMIT` and `OPENAI_EMPLOYER_DAILY_LIMIT` variables remain supported as fallback aliases. `OPENAI_MODEL` and `OPENAI_TIMEOUT` retain their existing purpose. Keys remain server-side and must not be added to browser code.

The shared AI HTTP client uses PHP cURL when available and otherwise makes HTTPS requests through PHP streams. For the Apache PHP runtime, enable either the cURL extension or both OpenSSL and `allow_url_fopen`; restarting Apache may be required after changing `php.ini`. Gemini transient HTTP 500/503 responses receive at most two bounded retries; a short `Retry-After` is honored, while longer delays are returned immediately for a clear retry-later message. HTTP 429 quota/rate-limit errors are reported separately without retrying. Model-related 404 responses show a length-limited, credential-redacted provider message when available. SMARTJOB does not switch providers when Gemini is unavailable or over quota.

The current workflows include configurable company document requirements, PDF/DOCX credential uploads with immutable per-application file snapshots, application status history, student withdrawal, interview scheduling, institution-scoped placement approval, recipient-scoped notifications and read state, and SMARTMESSAGE conversation previews with unread counts and AJAX replies. Remaining work includes company registration-specific legal fields and workplace media, complete placement progress tracking, weighted persisted SMARTMATCH recommendation pages, reports/exports, and broader administrative dashboards. Schema presence alone is not feature completion.

## Installation (XAMPP)

1. Copy the project to `C:\xampp\htdocs\smartjob`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin and import `database/smartjob_db.sql`, then import `database/002_student_communication.sql` into the same database.
4. Import `database/migrations/create_account_verifications.sql`, `database/migrations/create_verification_delivery_logs.sql`, and `database/migrations/create_api_provider_logs.sql` into the same database.
5. Import `database/migrations/create_saved_internships.sql` into the same database.
6. Import `database/migrations/create_saved_companies.sql` into the same database.
7. Import `database/migrations/add_company_document_reviews.sql` and `database/migrations/add_application_qualification_reviews.sql` into the same database.
8. Import `database/migrations/add_notification_links.sql` into the same database.
9. Import `database/migrations/create_application_document_snapshots.sql` into the same database.
10. Import `database/migrations/add_company_verification_requirements.sql` into the same database.
11. Import `database/migrations/add_application_interview_status.sql` into the same database.
12. Import `database/migrations/add_bulk_company_document_uploads.sql` into the same database.
13. Import `database/migrations/add_ai_writing_support.sql` into the same database.
14. Install maintained PHP packages from the project root using Composer: `composer install`. This enables Dompdf PDF export and PHPMailer SMTP support.
15. Configure `.env.example` by copying it to `.env` in the project root, or set its values in the Apache/PHP process environment. The local `.env` loader does not override existing process variables; `.env.example` itself is not loaded. Set `SMARTJOB_DB_HOST`, `SMARTJOB_DB_PORT`, `SMARTJOB_DB_NAME`, `SMARTJOB_DB_USER`, `SMARTJOB_DB_PASSWORD`, and a random `SMARTJOB_APP_KEY` of at least 32 characters. Standard `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` aliases are also accepted when their `SMARTJOB_DB_*` counterparts are unset. Defaults target a standard local XAMPP install (`127.0.0.1`, `smartjob_db`, `root`, empty password).
16. Configure Mailjet API and secret keys, or Brevo/SendGrid API credentials, and a verified sender; alternatively select SMTP and configure `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, and `SMTP_ENCRYPTION`. Configure TextBee's API key and pair an authorized Android gateway device, or set Twilio credentials. Credentials remain in the server environment or private `.env`.
17. Optionally set `SMARTJOB_PRIVATE_STORAGE` to a protected directory outside the web root; otherwise files are stored under `C:\xampp\private\smartjob`.
18. To enable AI writing with Gemini, configure `AI_PROVIDER=gemini` and `GEMINI_API_KEY` in the private `.env` or server environment. To use OpenAI instead, set `AI_PROVIDER=openai` and configure `OPENAI_API_KEY`.
19. Visit `http://localhost/smartjob/`, then choose **Create the initial administrator account**. That setup page disables itself after an administrator exists.
20. Register a student or employer account, then verify the required contact method. A newly registered company remains pending until an administrator completes the profile and document review.

For production, configure HTTPS, a dedicated least-privilege database account, a random application key, backups, and PHP error logging. Do not expose the project database to the public internet.

## Validation

Run PHP syntax validation with the XAMPP binary. Passwords are limited to 12–72 bytes to avoid bcrypt's input truncation in the default XAMPP PHP configuration.

```powershell
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe -l app\bootstrap.php
C:\xampp\php\php.exe -l app\Core\Router.php
C:\xampp\php\php.exe -l app\Modules\AccountVerification\Services\OtpService.php
C:\xampp\php\php.exe tests\AccountVerificationServiceTest.php
C:\xampp\php\php.exe tests\AccountVerificationRegistrationTest.php
C:\xampp\php\php.exe -l app\Modules\Authentication\Controllers\AuthenticationController.php
C:\xampp\php\php.exe -l app\Modules\Student\Controllers\StudentProfileController.php
C:\xampp\php\php.exe -l app\Modules\Credentials\Controllers\CredentialController.php
C:\xampp\php\php.exe -l app\Modules\Training\Controllers\TrainingController.php
C:\xampp\php\php.exe -l app\Modules\SmartMessage\Controllers\MessageController.php
C:\xampp\php\php.exe -l app\Modules\SmartMail\Controllers\EmailController.php
C:\xampp\php\php.exe -l app\Modules\Company\Controllers\CompanyController.php
C:\xampp\php\php.exe -l app\Modules\Internship\Controllers\InternshipController.php
C:\xampp\php\php.exe -l app\Modules\Applications\Controllers\ApplicationController.php
C:\xampp\php\php.exe -l app\Modules\Notifications\Controllers\NotificationController.php
C:\xampp\php\php.exe -l app\Core\Database.php
C:\xampp\php\php.exe tests\AuthenticationControllerTest.php
C:\xampp\php\php.exe tests\StudentProfileControllerTest.php
C:\xampp\php\php.exe tests\AccountSettingsControllerTest.php
C:\xampp\php\php.exe tests\ExploreControllerTest.php
C:\xampp\php\php.exe tests\ExploreRecommendationsTest.php
C:\xampp\php\php.exe tests\CompanyProfileControllerTest.php
C:\xampp\php\php.exe tests\CredentialControllerTest.php
C:\xampp\php\php.exe tests\TrainingControllerTest.php
C:\xampp\php\php.exe tests\TrainingVerificationControllerTest.php
C:\xampp\php\php.exe tests\MessageControllerTest.php
C:\xampp\php\php.exe tests\EmailControllerTest.php
C:\xampp\php\php.exe tests\EmailServiceTest.php
C:\xampp\php\php.exe tests\OpenAIWritingServiceTest.php
C:\xampp\php\php.exe tests\GeminiWritingServiceTest.php
C:\xampp\php\php.exe tests\AIWritingServiceFactoryTest.php
C:\xampp\php\php.exe tests\AIWritingControllerTest.php
C:\xampp\php\php.exe tests\CompanyControllerTest.php
C:\xampp\php\php.exe tests\InternshipControllerTest.php
C:\xampp\php\php.exe tests\ApplicationControllerTest.php
C:\xampp\php\php.exe tests\NotificationControllerTest.php
C:\xampp\php\php.exe tests\ApplicationDocumentSnapshotTest.php
C:\xampp\php\php.exe tests\CompanyVerificationRequirementsTest.php
C:\xampp\php\php.exe tests\PlacementControllerTest.php
C:\xampp\php\php.exe -l app\services\MatchingService.php
C:\xampp\php\php.exe -l app\views\page.php
C:\xampp\php\php.exe -l app\views\enhanced.php
C:\xampp\php\php.exe -l app\services\CredentialStorage.php
C:\xampp\php\php.exe -l app\services\EmailService.php
C:\xampp\php\php.exe tests\CoreDatabaseTest.php
C:\xampp\php\php.exe tests\MatchingServiceTest.php
```

The base schema and extension migration have been imported successfully into the local MariaDB 10.11 server. Exercise setup, login, registration, biodata, credentials, application document sharing, messaging, and SMTP draft behavior in your local installation. SMTP send/recipient delivery requires real provider credentials; a successful SMTP handoff is not a delivery receipt.
