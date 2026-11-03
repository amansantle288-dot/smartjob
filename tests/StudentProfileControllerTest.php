<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Student\Controllers\StudentProfileController;

$studentActions = [
    'save_profile',
    'save_education_background',
    'upload_profile_photo',
    'remove_profile_photo',
    'save_biodata',
    'add_education',
    'update_education',
    'delete_education',
    'add_project',
    'add_experience',
    'add_skill',
];

foreach ($studentActions as $action) {
    if (!StudentProfileController::supports($action)) {
        throw new RuntimeException("Student profile action is not registered: {$action}");
    }
}

foreach (['login', 'upload_credential', 'add_training'] as $action) {
    if (StudentProfileController::supports($action)) {
        throw new RuntimeException("Action is assigned to the wrong module: {$action}");
    }
}

$pdo = db();
$controller = new StudentProfileController();
$token = bin2hex(random_bytes(6));
$userId = 0;
$photoKey = null;
$photoPath = null;
$skillName = 'Test Skill ' . $token;

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, phone_number, password_hash, role, status) VALUES (?, ?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Profile Test', 'profile-' . $token . '@example.invalid', '+639171234567', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_student_profiles (user_id) VALUES (?)')->execute([$userId]);
    $photoKey = bin2hex(random_bytes(32));
    $photoPath = ProfilePhotoStorage::path($photoKey);
    $photoDirectory = dirname($photoPath);
    if (!is_dir($photoDirectory) && !mkdir($photoDirectory, 0700, true) && !is_dir($photoDirectory)) {
        throw new RuntimeException('Could not create profile photo test storage.');
    }
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    if (!is_string($png) || file_put_contents($photoPath, $png) === false) {
        throw new RuntimeException('Could not create a profile photo test image.');
    }
    $pdo->prepare('UPDATE tbl_student_profiles SET profile_photo_key=? WHERE user_id=?')->execute([$photoKey, $userId]);
    $_SESSION['user_id'] = $userId;

    $runAction = static function (string $action, array $post, string $page, string $notice) use ($controller): void {
        $_POST = $post;
        $result = $controller->handle($action);
        if ($result !== ['page' => $page, 'notice' => $notice]) {
            throw new RuntimeException("Unexpected response from student profile action: {$action}");
        }
    };

    $runAction('save_profile', ['program' => 'Information Technology', 'year_level' => '3rd Year', 'bio' => 'Profile controller integration test.'], 'profile', 'Profile saved.');
    $runAction('save_biodata', [
        'phone' => '555-0101',
        'address' => '10 Test Street',
        'city' => 'Test City',
        'province' => 'Test Province',
        'summary' => 'Private profile test',
        'template' => 'modern',
        'date_of_birth' => '2000-01-02',
        'visibility' => ['education', 'invalid_section'],
    ], 'biodata', 'Your e-Biodata was saved.');
    $runAction('save_education_background', [
        'elementary_school' => 'Test Elementary',
        'elementary_location' => 'Test Town',
        'elementary_year_started' => '2008',
        'elementary_year_graduated' => '2014',
        'elementary_honors' => 'Elementary honors',
        'junior_high_school' => 'Test Junior High',
        'junior_high_location' => 'Test Town',
        'junior_high_year_started' => '2014',
        'junior_high_year_completed' => '2018',
        'junior_high_honors' => 'Junior high honors',
        'senior_high_school' => 'Test Senior High',
        'senior_high_location' => 'Test City',
        'senior_high_strand' => 'STEM',
        'senior_high_year_started' => '2018',
        'senior_high_year_graduated' => '2020',
        'senior_high_honors' => 'Senior high honors',
        'senior_high_not_applicable' => '0',
        'tertiary_school' => 'Test University',
        'tertiary_course' => 'Information Technology',
        'tertiary_year_level' => '3rd Year',
        'tertiary_location' => 'Test City',
        'tertiary_year_started' => '2021',
        'tertiary_year_graduated' => '2025',
    ], 'biodata', 'Educational background saved.');
    $attainment = $pdo->prepare('SELECT elementary_school, elementary_honors, senior_high_strand, senior_high_honors, senior_high_not_applicable, tertiary_course, tertiary_year_graduated FROM tbl_student_educational_attainment WHERE user_id=?');
    $attainment->execute([$userId]);
    $attainmentRecord = $attainment->fetch();
    if (!$attainmentRecord || $attainmentRecord['elementary_school'] !== 'Test Elementary' || $attainmentRecord['elementary_honors'] !== 'Elementary honors' || $attainmentRecord['senior_high_strand'] !== 'STEM' || $attainmentRecord['senior_high_honors'] !== 'Senior high honors' || (int) $attainmentRecord['senior_high_not_applicable'] !== 0 || $attainmentRecord['tertiary_course'] !== 'Information Technology' || (int) $attainmentRecord['tertiary_year_graduated'] !== 2025) {
        throw new RuntimeException('Complete educational attainment was not saved.');
    }
    $runAction('add_education', [
        'school_name' => 'Test University',
        'education_program' => 'Information Technology',
        'department' => 'Computing',
        'education_year' => '3rd Year',
        'achievements' => 'Dean list',
        'education_start_date' => '2023-08-01',
        'education_end_date' => '',
    ], 'biodata', 'Education record added.');

    $educationId = (int) $pdo->query("SELECT id FROM tbl_student_education WHERE user_id = {$userId}")->fetchColumn();
    $runAction('delete_education', ['record_id' => $educationId], 'biodata', 'Education record removed.');

    $runAction('add_education', [
        'education_level' => 'tertiary',
        'school_name' => 'SMARTJOB University A ' . $token,
        'education_program' => 'Information Technology',
        'major' => 'Software Engineering',
        'location' => 'Test City',
        'education_year' => '3rd year',
        'start_year' => '2022',
        'expected_graduation_year' => '2027',
        'academic_status' => 'currently_enrolled',
        'is_current' => '1',
        'achievements' => 'Dean’s List',
    ], 'biodata', 'Education record added.');
    $firstEducationQuery = $pdo->prepare('SELECT id FROM tbl_student_education WHERE user_id=? AND school_name=?');
    $firstEducationQuery->execute([$userId, 'SMARTJOB University A ' . $token]);
    $firstEducationId = (int) $firstEducationQuery->fetchColumn();
    $syncedProfile = $pdo->prepare('SELECT program, year_level FROM tbl_student_profiles WHERE user_id=?');
    $syncedProfile->execute([$userId]);
    $syncedProfileValues = $syncedProfile->fetch();
    if (!$firstEducationId || !$syncedProfileValues || $syncedProfileValues['program'] !== 'Information Technology' || $syncedProfileValues['year_level'] !== '3rd year') {
        throw new RuntimeException('The current tertiary record was not synchronized to the SMARTMATCH profile.');
    }

    $runAction('add_education', [
        'education_level' => 'tertiary',
        'school_name' => 'SMARTJOB University B ' . $token,
        'education_program' => 'Computer Science',
        'major' => 'Data Science',
        'education_year' => '2nd year',
        'start_year' => '2024',
        'expected_graduation_year' => '2028',
        'academic_status' => 'undergraduate',
        'is_current' => '0',
    ], 'biodata', 'Education record added.');
    $secondEducationQuery = $pdo->prepare('SELECT id FROM tbl_student_education WHERE user_id=? AND school_name=?');
    $secondEducationQuery->execute([$userId, 'SMARTJOB University B ' . $token]);
    $secondEducationId = (int) $secondEducationQuery->fetchColumn();
    if (!$secondEducationId) {
        throw new RuntimeException('A second tertiary education record was not created.');
    }
    $tertiaryBiodata = load_biodata($userId);
    $data = $tertiaryBiodata;
    $visibility = $data['visibility'];
    ob_start();
    require __DIR__ . '/../app/views/biodata_content.php';
    $tertiaryBiodataHtml = (string) ob_get_clean();
    if (!str_contains($tertiaryBiodataHtml, 'SMARTJOB University A ' . $token) || !str_contains($tertiaryBiodataHtml, 'SMARTJOB University B ' . $token) || !str_contains($tertiaryBiodataHtml, 'Software Engineering')) {
        throw new RuntimeException('Multiple tertiary records or major details did not populate e-Biodata.');
    }
    ob_start();
    render_page('biodata', null, []);
    $completeEducationPageHtml = (string) ob_get_clean();
    if (!str_contains($completeEducationPageHtml, 'aria-valuenow="100"') || !str_contains($completeEducationPageHtml, 'data-education-completed-count>4</span>')) {
        throw new RuntimeException('Educational profile completion did not account for the complete fixed-level and tertiary records.');
    }
    $profileContainerStart = strpos($completeEducationPageHtml, 'class="sj-student-profile-container"');
    $profileSectionsStart = strpos($completeEducationPageHtml, 'class="sj-profile-sections"');
    $personalSection = strpos($completeEducationPageHtml, '<h2>Personal Information</h2>');
    $summarySection = strpos($completeEducationPageHtml, '<h3>Professional Summary</h3>');
    $educationSection = strpos($completeEducationPageHtml, 'id="education-background"');
    $historySection = strpos($completeEducationPageHtml, 'Educational History');
    $skillsSection = strpos($completeEducationPageHtml, 'class="card form-card sj-profile-section sj-profile-skills"');
    $projectsSection = strpos($completeEducationPageHtml, 'class="card form-card sj-profile-section sj-profile-projects"');
    $previewSection = strpos($completeEducationPageHtml, 'id="biodata-preview"');
    if ($profileContainerStart === false || $profileSectionsStart === false || !($profileContainerStart < $profileSectionsStart && $profileSectionsStart < $personalSection && $personalSection < $summarySection && $summarySection < $educationSection && $educationSection < $historySection && $historySection < $skillsSection && $skillsSection < $projectsSection && $projectsSection < $previewSection)) {
        throw new RuntimeException('Student profile sections are not rendered in the shared single-column layout and expected order.');
    }
    foreach (['class="subform sj-profile-skill-form"', 'class="card form-card sj-profile-section sj-profile-projects"'] as $expected) {
        if (!str_contains($completeEducationPageHtml, $expected)) {
            throw new RuntimeException('Student profile layout is missing responsive skills or structured project/experience markup: ' . $expected);
        }
    }
    $tertiarySectionStart = strpos($completeEducationPageHtml, '<section class="card education-tertiary-section"');
    $tertiaryHeaderEnd = $tertiarySectionStart === false ? false : strpos($completeEducationPageHtml, '</header>', $tertiarySectionStart);
    $tertiaryFormStart = $tertiarySectionStart === false ? false : strpos($completeEducationPageHtml, 'data-tertiary-form-wrapper', $tertiarySectionStart);
    $tertiaryRecordsStart = $tertiarySectionStart === false ? false : strpos($completeEducationPageHtml, 'Existing Tertiary Education Records', $tertiarySectionStart);
    if ($tertiaryHeaderEnd === false || $tertiaryFormStart === false || $tertiaryRecordsStart === false || !($tertiaryHeaderEnd < $tertiaryFormStart && $tertiaryFormStart < $tertiaryRecordsStart)) {
        throw new RuntimeException('The tertiary section is not stacked as header, full-width form, then existing records.');
    }
    if (!str_contains($completeEducationPageHtml, 'data-edit-tertiary="' . $firstEducationId . '"') || !str_contains($completeEducationPageHtml, 'data-edit-tertiary="' . $secondEducationId . '"')) {
        throw new RuntimeException('Existing tertiary records are missing their edit actions.');
    }
    foreach (['Years attended', 'Academic status', 'Location', 'Edit record', 'education-record-edit-' . $firstEducationId, 'education-record-edit-' . $secondEducationId] as $expected) {
        if (!str_contains($completeEducationPageHtml, $expected)) {
            throw new RuntimeException('Tertiary record management is missing structured detail or edit controls: ' . $expected);
        }
    }
    $fixedEducationQuery = $pdo->prepare('SELECT elementary_school, junior_high_school, senior_high_school, senior_high_not_applicable FROM tbl_student_educational_attainment WHERE user_id=?');
    $fixedEducationQuery->execute([$userId]);
    $fixedEducationBeforePartialRender = $fixedEducationQuery->fetch();
    $pdo->prepare('UPDATE tbl_student_educational_attainment SET elementary_school=NULL, junior_high_school=NULL, senior_high_school=NULL, senior_high_not_applicable=0 WHERE user_id=?')->execute([$userId]);
    ob_start();
    render_page('biodata', null, []);
    $tertiaryOnlyPageHtml = (string) ob_get_clean();
    $pdo->prepare('UPDATE tbl_student_educational_attainment SET elementary_school=?, junior_high_school=?, senior_high_school=?, senior_high_not_applicable=? WHERE user_id=?')
        ->execute([
            $fixedEducationBeforePartialRender['elementary_school'],
            $fixedEducationBeforePartialRender['junior_high_school'],
            $fixedEducationBeforePartialRender['senior_high_school'],
            $fixedEducationBeforePartialRender['senior_high_not_applicable'],
            $userId,
        ]);
    if (!str_contains($tertiaryOnlyPageHtml, 'aria-valuenow="25"') || !str_contains($tertiaryOnlyPageHtml, 'data-education-completed-count>1</span>')) {
        throw new RuntimeException('A tertiary-only education record was incorrectly presented as a fully complete education profile.');
    }

    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_POST = [
        'record_id' => (string) $secondEducationId,
        'education_level' => 'tertiary',
        'school_name' => 'SMARTJOB University B ' . $token,
        'education_program' => 'Computer Science',
        'major' => 'Data Science',
        'education_year' => '3rd year',
        'start_year' => '2024',
        'expected_graduation_year' => '2028',
        'academic_status' => 'currently_enrolled',
        'is_current' => '1',
    ];
    $updateResult = $controller->handle('update_education');
    unset($_SERVER['HTTP_ACCEPT']);
    if (($updateResult['json']['operation'] ?? '') !== 'update' || (int) ($updateResult['json']['record_id'] ?? 0) !== $secondEducationId) {
        throw new RuntimeException('The asynchronous education edit response is missing its updated record.');
    }
    $syncedProfile->execute([$userId]);
    $syncedProfileValues = $syncedProfile->fetch();
    if (!$syncedProfileValues || $syncedProfileValues['program'] !== 'Computer Science' || $syncedProfileValues['year_level'] !== '3rd year') {
        throw new RuntimeException('Editing the current tertiary record did not synchronize SMARTMATCH fields.');
    }

    $_POST = [
        'record_id' => (string) $secondEducationId,
        'education_level' => 'tertiary',
        'school_name' => 'SMARTJOB University B ' . $token,
        'education_program' => 'Computer Science',
        'start_year' => '2025',
        'actual_graduation_year' => '2024',
        'academic_status' => 'graduated',
        'is_current' => '0',
    ];
    try {
        $controller->handle('update_education');
        throw new RuntimeException('Education validation accepted a graduation year earlier than the start year.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Graduation year cannot be earlier than the year started.') {
            throw $exception;
        }
    }

    $_POST = ['record_id' => (string) ($secondEducationId + 999999), 'education_level' => 'tertiary', 'school_name' => 'Other student record', 'education_program' => 'Other'];
    try {
        $controller->handle('update_education');
        throw new RuntimeException('Education editing accepted a record not owned by the authenticated student.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'This education record is not available to edit.') {
            throw $exception;
        }
    }

    $runAction('delete_education', ['record_id' => $secondEducationId], 'biodata', 'Education record removed.');
    $runAction('delete_education', ['record_id' => $firstEducationId], 'biodata', 'Education record removed.');
    $runAction('add_project', [
        'project_title' => 'Profile test project',
        'project_role' => 'Developer',
        'project_description' => 'A test project record',
        'technologies' => 'PHP',
        'project_url' => 'https://example.invalid/project',
        'project_start_date' => '',
        'project_end_date' => '',
    ], 'biodata', 'Project added.');
    $runAction('add_experience', [
        'experience_title' => 'Student Developer',
        'organization' => 'Test Organization',
        'experience_description' => 'A test experience record',
        'experience_start_date' => '',
        'experience_end_date' => '',
    ], 'biodata', 'Experience added.');
    $runAction('add_skill', ['skill_name' => $skillName, 'proficiency' => 'intermediate'], 'biodata', 'Skill saved to your profile and matching profile.');

    if ($pdo->query("SELECT program FROM tbl_student_profiles WHERE user_id = {$userId}")->fetchColumn() !== 'Information Technology') {
        throw new RuntimeException('Student profile was not saved.');
    }
    $visibility = $pdo->query("SELECT visibility FROM tbl_student_biodata WHERE user_id = {$userId}")->fetchColumn();
    if (json_decode((string) $visibility, true, 512, JSON_THROW_ON_ERROR) !== ['education']) {
        throw new RuntimeException('Biodata section visibility was not validated.');
    }
    $biodata = load_biodata($userId);
    if (($biodata['education_background']['elementary_school'] ?? '') !== 'Test Elementary' || ($biodata['education_background']['tertiary_course'] ?? '') !== 'Information Technology' || $biodata['phone'] !== '+639171234567' || !str_starts_with((string) ($biodata['photo_data_uri'] ?? ''), 'data:image/png;base64,')) {
        throw new RuntimeException('Saved educational attainment or profile photo was not loaded into biodata.');
    }
    $data = $biodata;
    $visibility = $data['visibility'];
    ob_start();
    require __DIR__ . '/../app/views/biodata_content.php';
    $biodataHtml = (string) ob_get_clean();
    if (!str_contains($biodataHtml, 'Test Elementary') || !str_contains($biodataHtml, 'Information Technology') || !str_contains($biodataHtml, 'Test Senior High') || !str_contains($biodataHtml, 'class="biodata-photo"')) {
        throw new RuntimeException('Educational attainment or profile photo was not rendered in the biodata preview/export view.');
    }
    ob_start();
    render_page('biodata', null, []);
    $educationPageHtml = (string) ob_get_clean();
    foreach (['education-page-container', 'Educational Background', 'Educational Profile Completion', 'Primary Education', 'Junior High School', 'Senior High School', 'Tertiary Education', 'Educational History', 'education-progressbar', 'data-tertiary-form-wrapper hidden', 'data-tertiary-form', 'data-open-tertiary-form', 'data-cancel-tertiary-form', 'Add Tertiary Record', 'Existing Tertiary Education Records', 'education-tertiary-form-grid', 'name="senior_high_not_applicable"', 'name="academic_status"', 'name="education_level"', 'assets/css/app.css?v=', 'assets/js/education.js?v='] as $expected) {
        if (!str_contains($educationPageHtml, $expected)) {
            throw new RuntimeException('The unified education interface is missing expected content: ' . $expected);
        }
    }
    if (str_contains($educationPageHtml, '<h2>Educational attainment</h2>') || !str_contains($educationPageHtml, 'education.js')) {
        throw new RuntimeException('The unified education interface contains duplicate legacy sections or lacks its AJAX handler.');
    }
    $runAction('save_education_background', ['senior_high_not_applicable' => '1'], 'biodata', 'Educational background saved.');
    $notApplicableQuery = $pdo->prepare('SELECT senior_high_not_applicable, senior_high_school FROM tbl_student_educational_attainment WHERE user_id=?');
    $notApplicableQuery->execute([$userId]);
    $notApplicable = $notApplicableQuery->fetch();
    $notApplicableBiodata = load_biodata($userId);
    $data = $notApplicableBiodata;
    $visibility = $data['visibility'];
    ob_start();
    require __DIR__ . '/../app/views/biodata_content.php';
    $notApplicableHtml = (string) ob_get_clean();
    if (!$notApplicable || (int) $notApplicable['senior_high_not_applicable'] !== 1 || $notApplicable['senior_high_school'] !== 'Test Senior High' || !str_contains($notApplicableHtml, 'Not applicable to this educational pathway.') || str_contains($notApplicableHtml, 'Test Senior High')) {
        throw new RuntimeException('Senior high school applicability was not stored without discarding history or rendered consistently in biodata.');
    }
    foreach (['tbl_student_education', 'tbl_student_projects', 'tbl_student_experiences', 'tbl_student_skills'] as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM {$table} WHERE " . ($table === 'tbl_student_skills' ? 'student_id' : 'user_id') . " = {$userId}")->fetchColumn();
        if ((int) $count !== ($table === 'tbl_student_education' ? 0 : 1)) {
            throw new RuntimeException("Unexpected profile record count in {$table}.");
        }
    }
    $runAction('save_education_background', ['elementary_school' => '', 'elementary_location' => ''], 'biodata', 'Educational background saved.');
    $optionalSchool = $pdo->prepare('SELECT elementary_school FROM tbl_student_educational_attainment WHERE user_id=?');
    $optionalSchool->execute([$userId]);
    if ($optionalSchool->fetchColumn() !== null) {
        throw new RuntimeException('An incomplete optional education section could not be saved.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    $pdo->beginTransaction();
    try {
        if ($userId > 0) {
            $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM tbl_student_skills WHERE student_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM tbl_users WHERE id = ?')->execute([$userId]);
        }
        $pdo->prepare('DELETE FROM tbl_skills WHERE name = ?')->execute([$skillName]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    if (is_string($photoPath) && is_file($photoPath)) {
        unlink($photoPath);
    }
    if (isset($photoDirectory) && is_dir($photoDirectory) && count(scandir($photoDirectory)) === 2) {
        rmdir($photoDirectory);
    }
}

echo "Student profile action routing and database operations verified.\n";
