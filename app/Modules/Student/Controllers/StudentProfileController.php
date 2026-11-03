<?php
declare(strict_types=1);

namespace SMARTJOB\Modules\Student\Controllers;

use PDO;
use RuntimeException;

final class StudentProfileController
{
    private const ACTIONS = [
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

    public static function supports(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    /**
     * @return array{page: string, notice: string}
     */
    public function handle(string $action): array
    {
        return match ($action) {
            'save_profile' => $this->saveProfile(),
            'save_education_background' => $this->saveEducationBackground(),
            'upload_profile_photo' => $this->uploadProfilePhoto(),
            'remove_profile_photo' => $this->removeProfilePhoto(),
            'save_biodata' => $this->saveBiodata(),
            'add_education' => $this->addEducation(),
            'update_education' => $this->updateEducation(),
            'delete_education' => $this->deleteEducation(),
            'add_project' => $this->addProject(),
            'add_experience' => $this->addExperience(),
            'add_skill' => $this->addSkill(),
            default => throw new RuntimeException('Unknown student profile action.'),
        };
    }

    public function exportPdf(): never
    {
        \require_role('student');
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new RuntimeException('PDF export requires the Composer dependencies. Run composer install and try again.');
        }

        $data = \load_biodata((int) \auth_user()['id']);
        ob_start();
        require dirname(__DIR__, 3) . '/views/biodata_document.php';
        $html = (string) ob_get_clean();
        $pdf = new \Dompdf\Dompdf();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->stream('smartjob-biodata.pdf', ['Attachment' => true]);
        exit;
    }

    public function streamProfilePhoto(): never
    {
        \require_role('student');
        $userId = (int) \auth_user()['id'];
        $query = \db()->prepare('SELECT profile_photo_key FROM tbl_student_profiles WHERE user_id=?');
        $query->execute([$userId]);
        $key = $query->fetchColumn();
        $path = is_string($key) && $key !== '' ? \ProfilePhotoStorage::path($key) : '';
        $mime = $path !== '' && is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/png'], true)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function saveProfile(): array
    {
        $userId = $this->studentId();
        $profileQuery = \db()->prepare('SELECT institution_id, program, year_level FROM tbl_student_profiles WHERE user_id=?');
        $profileQuery->execute([$userId]);
        $currentProfile = $profileQuery->fetch() ?: [];
        $program = trim((string) ($_POST['program'] ?? $currentProfile['program'] ?? ''));
        $year = trim((string) ($_POST['year_level'] ?? $currentProfile['year_level'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $preferredLocation = trim((string) ($_POST['preferred_location'] ?? ''));
        $workMode = (string) ($_POST['preferred_work_mode'] ?? 'any');
        $institutionInput = trim((string) ($_POST['institution_id'] ?? ''));
        $institutionId = $institutionInput === '' ? null : filter_var($institutionInput, FILTER_VALIDATE_INT);
        if (mb_strlen($program) > 160 || mb_strlen($year) > 40 || mb_strlen($bio) > 2000 || mb_strlen($preferredLocation) > 180 || !in_array($workMode, ['onsite', 'hybrid', 'remote', 'any'], true) || ($institutionInput !== '' && (!$institutionId || $institutionId < 1))) {
            throw new RuntimeException('One or more profile fields exceed the allowed length.');
        }
        if ($institutionId) {
            $institutionQuery = \db()->prepare("SELECT id FROM tbl_institutions WHERE id=? AND status='active'");
            $institutionQuery->execute([$institutionId]);
            if (!$institutionQuery->fetchColumn()) {
                throw new RuntimeException('Select an active educational institution.');
            }
        }

        $currentInstitution = array_key_exists('institution_id', $_POST)
            ? $institutionId
            : ($currentProfile['institution_id'] ?? null);
        \db()->prepare('UPDATE tbl_student_profiles SET institution_id=?, program = ?, year_level = ?, bio = ?, preferred_location = ?, preferred_work_mode = ? WHERE user_id = ?')
            ->execute([$currentInstitution, $program ?: null, $year ?: null, $bio ?: null, $preferredLocation ?: null, $workMode, $userId]);

        return ['page' => 'profile', 'notice' => 'Profile saved.'];
    }

    private function saveBiodata(): array
    {
        $userId = $this->studentId();
        $phone = (string) (\auth_user()['phone_number'] ?? '');
        $address = trim((string) ($_POST['address'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $province = trim((string) ($_POST['province'] ?? ''));
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $template = (string) ($_POST['template'] ?? 'standard');
        $dateOfBirth = trim((string) ($_POST['date_of_birth'] ?? ''));
        if (mb_strlen($phone) > 40 || mb_strlen($address) > 255 || mb_strlen($city) > 120 || mb_strlen($province) > 120 || mb_strlen($summary) > 3000 || !in_array($template, ['standard', 'modern'], true)) {
            throw new RuntimeException('Check field lengths and select a valid biodata template.');
        }
        if ($dateOfBirth !== '' && !\valid_date($dateOfBirth)) {
            throw new RuntimeException('Enter a valid date of birth.');
        }

        $visibility = array_values(array_intersect(
            ['education', 'experience', 'projects', 'skills', 'training', 'certifications', 'date_of_birth'],
            (array) ($_POST['visibility'] ?? [])
        ));
        \db()->prepare('INSERT INTO tbl_student_biodata (user_id, date_of_birth, phone, address, city, province, summary, template, visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date_of_birth=VALUES(date_of_birth), phone=VALUES(phone), address=VALUES(address), city=VALUES(city), province=VALUES(province), summary=VALUES(summary), template=VALUES(template), visibility=VALUES(visibility)')
            ->execute([
                $userId,
                $dateOfBirth ?: null,
                $phone ?: null,
                $address ?: null,
                $city ?: null,
                $province ?: null,
                $summary ?: null,
                $template,
                json_encode($visibility, JSON_THROW_ON_ERROR),
            ]);
        \audit_log($userId, 'biodata.updated', 'user', $userId);

        return ['page' => 'biodata', 'notice' => 'Your e-Biodata was saved.'];
    }

    private function saveEducationBackground(): array
    {
        $userId = $this->studentId();
        $textFields = [
            'elementary_school' => 190,
            'elementary_location' => 255,
            'elementary_honors' => 2000,
            'junior_high_school' => 190,
            'junior_high_location' => 255,
            'junior_high_honors' => 2000,
            'senior_high_school' => 190,
            'senior_high_location' => 255,
            'senior_high_strand' => 120,
            'senior_high_honors' => 2000,
            'tertiary_school' => 190,
            'tertiary_course' => 160,
            'tertiary_year_level' => 40,
            'tertiary_location' => 255,
        ];
        $yearFields = [
            'elementary_year_started',
            'elementary_year_graduated',
            'junior_high_year_started',
            'junior_high_year_completed',
            'senior_high_year_started',
            'senior_high_year_graduated',
            'tertiary_year_started',
            'tertiary_year_graduated',
        ];
        $existingQuery = \db()->prepare('SELECT * FROM tbl_student_educational_attainment WHERE user_id=?');
        $existingQuery->execute([$userId]);
        $existing = $existingQuery->fetch() ?: [];
        $values = [];
        foreach ($textFields as $field => $maxLength) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $value = trim((string) $_POST[$field]);
            if (mb_strlen($value) > $maxLength) {
                throw new RuntimeException('An educational background field exceeds its allowed length.');
            }
            $values[$field] = $value !== '' ? $value : null;
        }
        foreach ($yearFields as $field) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $raw = trim((string) $_POST[$field]);
            if ($raw === '') {
                $values[$field] = null;
                continue;
            }
            $maxYear = $field === 'tertiary_year_graduated' ? (int) date('Y') + 15 : (int) date('Y');
            $year = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => $maxYear]]);
            if ($year === false) {
                throw new RuntimeException('Enter a valid education year.');
            }
            $values[$field] = $year;
        }
        if (array_key_exists('senior_high_not_applicable', $_POST)) {
            $notApplicable = (string) $_POST['senior_high_not_applicable'];
            if (!in_array($notApplicable, ['0', '1'], true)) {
                throw new RuntimeException('Select a valid senior high school option.');
            }
            $values['senior_high_not_applicable'] = (int) $notApplicable;
        }
        foreach ([
            ['elementary_year_started', 'elementary_year_graduated'],
            ['junior_high_year_started', 'junior_high_year_completed'],
            ['senior_high_year_started', 'senior_high_year_graduated'],
            ['tertiary_year_started', 'tertiary_year_graduated'],
        ] as [$startField, $endField]) {
            $start = array_key_exists($startField, $values) ? $values[$startField] : ($existing[$startField] ?? null);
            $end = array_key_exists($endField, $values) ? $values[$endField] : ($existing[$endField] ?? null);
            if ($start && $end && (int) $end < (int) $start) {
                throw new RuntimeException('Graduation year cannot be earlier than the year started.');
            }
        }
        if (!$values) {
            throw new RuntimeException('Choose an educational background field to save.');
        }
        $fields = array_keys($values);
        $columns = implode(', ', $fields);
        $placeholders = implode(', ', array_fill(0, count($fields) + 1, '?'));
        $updates = implode(', ', array_map(static fn (string $field): string => "{$field}=VALUES({$field})", $fields));
        \db_transaction(function (PDO $pdo) use ($userId, $columns, $placeholders, $updates, $values): void {
            $pdo->prepare("INSERT INTO tbl_student_educational_attainment (user_id, {$columns}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updates}")
                ->execute(array_merge([$userId], array_values($values)));
            \audit_log($userId, 'education.background_updated', 'user', $userId);
        });
        return $this->educationResponse('biodata', 'Educational background saved.', ['background' => $values]);
    }

    private function uploadProfilePhoto(): array
    {
        $userId = $this->studentId();
        $query = \db()->prepare('SELECT profile_photo_key FROM tbl_student_profiles WHERE user_id=?');
        $query->execute([$userId]);
        $oldKey = $query->fetchColumn();
        $file = \ProfilePhotoStorage::store($_FILES['profile_photo'] ?? []);
        try {
            \db_transaction(function (PDO $pdo) use ($file, $userId): void {
                $pdo->prepare('UPDATE tbl_student_profiles SET profile_photo_key=? WHERE user_id=?')
                    ->execute([$file['key'], $userId]);
                \audit_log($userId, 'profile.photo_updated', 'user', $userId);
            });
        } catch (\Throwable $exception) {
            \ProfilePhotoStorage::remove($file['key']);
            throw $exception;
        }
        if (is_string($oldKey) && $oldKey !== '' && $oldKey !== $file['key']) {
            \ProfilePhotoStorage::remove($oldKey);
        }
        return ['page' => 'account_settings', 'notice' => 'Profile photo updated.'];
    }

    private function removeProfilePhoto(): array
    {
        $userId = $this->studentId();
        $query = \db()->prepare('SELECT profile_photo_key FROM tbl_student_profiles WHERE user_id=?');
        $query->execute([$userId]);
        $oldKey = $query->fetchColumn();
        \db_transaction(function (PDO $pdo) use ($userId): void {
            $pdo->prepare('UPDATE tbl_student_profiles SET profile_photo_key=NULL WHERE user_id=?')->execute([$userId]);
            \audit_log($userId, 'profile.photo_removed', 'user', $userId);
        });
        if (is_string($oldKey) && $oldKey !== '') {
            \ProfilePhotoStorage::remove($oldKey);
        }
        return ['page' => 'account_settings', 'notice' => 'Profile photo removed.'];
    }

    private function addEducation(): array
    {
        $userId = $this->studentId();
        if (!array_key_exists('education_level', $_POST)) {
            return $this->addLegacyEducation($userId);
        }
        $record = $this->validatedEducationRecord();
        $recordId = 0;
        \db_transaction(function (PDO $pdo) use ($userId, $record): void {
            if ($record['is_current']) {
                $pdo->prepare("UPDATE tbl_student_education SET is_current=0 WHERE user_id=? AND education_level='tertiary'")
                    ->execute([$userId]);
            }
            $pdo->prepare('INSERT INTO tbl_student_education (user_id, education_level, school_name, location, program, department, major, year_level, achievements, start_date, end_date, expected_graduation_year, academic_status, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge([$userId], array_values($record)));
            $this->syncCurrentEducation($pdo, $userId);
            \audit_log($userId, 'education.record_added', 'user', $userId);
        });
        $recordId = (int) \db()->lastInsertId();

        return $this->educationResponse('biodata', 'Education record added.', ['operation' => 'add', 'record_id' => $recordId, 'record' => $record]);
    }

    private function updateEducation(): array
    {
        $userId = $this->studentId();
        $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$recordId || $recordId < 1) {
            throw new RuntimeException('Select a valid education record to update.');
        }
        $record = $this->validatedEducationRecord();
        \db_transaction(function (PDO $pdo) use ($userId, $recordId, $record): void {
            $existing = $pdo->prepare("SELECT id FROM tbl_student_education WHERE id=? AND user_id=? AND education_level='tertiary' FOR UPDATE");
            $existing->execute([$recordId, $userId]);
            if (!$existing->fetchColumn()) {
                throw new RuntimeException('This education record is not available to edit.');
            }
            if ($record['is_current']) {
                $pdo->prepare("UPDATE tbl_student_education SET is_current=0 WHERE user_id=? AND education_level='tertiary'")
                    ->execute([$userId]);
            }
            $update = $pdo->prepare('UPDATE tbl_student_education SET school_name=?, location=?, program=?, department=?, major=?, year_level=?, achievements=?, start_date=?, end_date=?, expected_graduation_year=?, academic_status=?, is_current=? WHERE id=? AND user_id=?');
            $update->execute([...array_values(array_slice($record, 1)), $recordId, $userId]);
            $this->syncCurrentEducation($pdo, $userId);
            \audit_log($userId, 'education.record_updated', 'user', $userId);
        });

        return $this->educationResponse('biodata', 'Education record updated.', ['operation' => 'update', 'record_id' => $recordId, 'record' => $record]);
    }

    private function deleteEducation(): array
    {
        $userId = $this->studentId();
        $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$recordId || $recordId < 1) {
            throw new RuntimeException('Select a valid education record to remove.');
        }
        \db_transaction(function (PDO $pdo) use ($recordId, $userId): void {
            $delete = $pdo->prepare("DELETE FROM tbl_student_education WHERE id=? AND user_id=? AND education_level='tertiary'");
            $delete->execute([$recordId, $userId]);
            if ($delete->rowCount() !== 1) {
                throw new RuntimeException('This education record could not be removed.');
            }
            $this->syncCurrentEducation($pdo, $userId);
            \audit_log($userId, 'education.record_removed', 'user', $userId);
        });

        return $this->educationResponse('biodata', 'Education record removed.', ['operation' => 'delete', 'record_id' => $recordId]);
    }

    private function addLegacyEducation(int $userId): array
    {
        $school = trim((string) ($_POST['school_name'] ?? ''));
        $program = trim((string) ($_POST['education_program'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $year = trim((string) ($_POST['education_year'] ?? ''));
        $achievements = trim((string) ($_POST['achievements'] ?? ''));
        $startDate = trim((string) ($_POST['education_start_date'] ?? ''));
        $endDate = trim((string) ($_POST['education_end_date'] ?? ''));
        if ($school === '' || $program === '' || mb_strlen($school) > 190 || mb_strlen($program) > 160 || mb_strlen($department) > 160 || mb_strlen($year) > 40 || mb_strlen($achievements) > 2000 || ($startDate !== '' && !\valid_date($startDate)) || ($endDate !== '' && !\valid_date($endDate))) {
            throw new RuntimeException('Enter a school and program, and keep each field within its length limit.');
        }

        \db()->prepare('INSERT INTO tbl_student_education (user_id, school_name, program, department, year_level, achievements, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $school, $program, $department ?: null, $year ?: null, $achievements ?: null, $startDate ?: null, $endDate ?: null]);

        return ['page' => 'biodata', 'notice' => 'Education record added.'];
    }

    private function validatedEducationRecord(): array
    {
        $school = trim((string) ($_POST['school_name'] ?? ''));
        $program = trim((string) ($_POST['education_program'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $major = trim((string) ($_POST['major'] ?? ''));
        $yearLevel = trim((string) ($_POST['education_year'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $achievements = trim((string) ($_POST['achievements'] ?? ''));
        $status = (string) ($_POST['academic_status'] ?? 'currently_enrolled');
        $isCurrentValue = (string) ($_POST['is_current'] ?? '0');
        $isCurrent = $isCurrentValue === '1';
        $startYear = trim((string) ($_POST['start_year'] ?? ''));
        $actualGraduationYear = trim((string) ($_POST['actual_graduation_year'] ?? ''));
        $expectedGraduationYear = trim((string) ($_POST['expected_graduation_year'] ?? ''));

        if ($school === '' || $program === '' || mb_strlen($school) > 190 || mb_strlen($program) > 160 || mb_strlen($department) > 160 || mb_strlen($major) > 160 || mb_strlen($yearLevel) > 40 || mb_strlen($location) > 255 || mb_strlen($achievements) > 2000 || !in_array($status, ['currently_enrolled', 'graduated', 'undergraduate', 'transferred', 'on_leave'], true) || !in_array($isCurrentValue, ['0', '1'], true)) {
            throw new RuntimeException('Enter a school and degree program, then check the other education fields.');
        }
        $start = $this->validatedEducationYear($startYear, false);
        $actual = $this->validatedEducationYear($actualGraduationYear, false);
        $expected = $this->validatedEducationYear($expectedGraduationYear, true);
        if ($actual !== null && $expected !== null) {
            throw new RuntimeException('Enter an actual graduation year or an expected graduation year, not both.');
        }
        if ($status === 'currently_enrolled' && $actual !== null) {
            throw new RuntimeException('Currently enrolled students should leave actual graduation year blank.');
        }
        if ($status === 'graduated' && $expected !== null) {
            throw new RuntimeException('Graduated students should enter an actual graduation year instead of an expected year.');
        }
        $endYear = $actual ?? $expected;
        if ($start !== null && $endYear !== null && $endYear < $start) {
            throw new RuntimeException('Graduation year cannot be earlier than the year started.');
        }
        $startDate = $start === null ? null : sprintf('%04d-01-01', $start);
        $endDate = $status === 'graduated' && $actual !== null ? sprintf('%04d-01-01', $actual) : null;

        return [
            'education_level' => 'tertiary',
            'school_name' => $school,
            'location' => $location ?: null,
            'program' => $program,
            'department' => $department ?: null,
            'major' => $major ?: null,
            'year_level' => $yearLevel ?: null,
            'achievements' => $achievements ?: null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'expected_graduation_year' => $expected,
            'academic_status' => $status,
            'is_current' => $isCurrent ? 1 : 0,
        ];
    }

    private function validatedEducationYear(string $value, bool $allowFuture): ?int
    {
        if ($value === '') {
            return null;
        }
        $maxYear = (int) date('Y') + ($allowFuture ? 15 : 0);
        $year = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => $maxYear]]);
        if ($year === false) {
            throw new RuntimeException($allowFuture
                ? 'Enter a valid expected graduation year.'
                : 'Enter a valid year no later than the current year.');
        }

        return $year;
    }

    private function syncCurrentEducation(PDO $pdo, int $userId): void
    {
        $query = $pdo->prepare("SELECT school_name, program, major, location, year_level, start_date, end_date, expected_graduation_year, academic_status FROM tbl_student_education WHERE user_id=? AND education_level='tertiary' ORDER BY is_current DESC, COALESCE(end_date, start_date) DESC, id DESC LIMIT 1");
        $query->execute([$userId]);
        $current = $query->fetch();
        if (!$current) {
            return;
        }
        $pdo->prepare('UPDATE tbl_student_profiles SET program=?, year_level=? WHERE user_id=?')
            ->execute([$current['program'], $current['year_level'], $userId]);
        $graduationYear = $current['academic_status'] === 'graduated'
            ? ($current['end_date'] ? (int) substr((string) $current['end_date'], 0, 4) : null)
            : ($current['expected_graduation_year'] ? (int) $current['expected_graduation_year'] : null);
        $startYear = $current['start_date'] ? (int) substr((string) $current['start_date'], 0, 4) : null;
        $pdo->prepare('INSERT INTO tbl_student_educational_attainment (user_id, tertiary_school, tertiary_course, tertiary_year_level, tertiary_location, tertiary_year_started, tertiary_year_graduated) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE tertiary_school=VALUES(tertiary_school), tertiary_course=VALUES(tertiary_course), tertiary_year_level=VALUES(tertiary_year_level), tertiary_location=VALUES(tertiary_location), tertiary_year_started=VALUES(tertiary_year_started), tertiary_year_graduated=VALUES(tertiary_year_graduated)')
            ->execute([$userId, $current['school_name'], $current['program'], $current['year_level'], $current['location'], $startYear, $graduationYear]);
    }

    private function educationResponse(string $page, string $notice, array $json): array
    {
        $result = ['page' => $page, 'notice' => $notice];
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            $result['json'] = $json;
        }

        return $result;
    }

    private function addProject(): array
    {
        $userId = $this->studentId();
        $title = trim((string) ($_POST['project_title'] ?? ''));
        $role = trim((string) ($_POST['project_role'] ?? ''));
        $description = trim((string) ($_POST['project_description'] ?? ''));
        $technologies = trim((string) ($_POST['technologies'] ?? ''));
        $url = trim((string) ($_POST['project_url'] ?? ''));
        $startDate = trim((string) ($_POST['project_start_date'] ?? ''));
        $endDate = trim((string) ($_POST['project_end_date'] ?? ''));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($title === '' || mb_strlen($title) > 180 || mb_strlen($role) > 120 || mb_strlen($description) > 3000 || mb_strlen($technologies) > 500 || ($startDate !== '' && !\valid_date($startDate)) || ($endDate !== '' && !\valid_date($endDate)) || ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)))) {
            throw new RuntimeException('Enter a valid project title and, if supplied, a valid HTTP(S) portfolio URL.');
        }

        \db()->prepare('INSERT INTO tbl_student_projects (user_id, title, role, description, technologies, project_url, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $title, $role ?: null, $description ?: null, $technologies ?: null, $url ?: null, $startDate ?: null, $endDate ?: null]);

        return ['page' => 'biodata', 'notice' => 'Project added.'];
    }

    private function addExperience(): array
    {
        $userId = $this->studentId();
        $title = trim((string) ($_POST['experience_title'] ?? ''));
        $organization = trim((string) ($_POST['organization'] ?? ''));
        $description = trim((string) ($_POST['experience_description'] ?? ''));
        $startDate = trim((string) ($_POST['experience_start_date'] ?? ''));
        $endDate = trim((string) ($_POST['experience_end_date'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180 || mb_strlen($organization) > 180 || mb_strlen($description) > 3000 || ($startDate !== '' && !\valid_date($startDate)) || ($endDate !== '' && !\valid_date($endDate))) {
            throw new RuntimeException('Enter an experience title and valid details.');
        }

        \db()->prepare('INSERT INTO tbl_student_experiences (user_id, title, organization, description, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $title, $organization ?: null, $description ?: null, $startDate ?: null, $endDate ?: null]);

        return ['page' => 'biodata', 'notice' => 'Experience added.'];
    }

    private function addSkill(): array
    {
        $userId = $this->studentId();
        $name = trim((string) ($_POST['skill_name'] ?? ''));
        $description = trim((string) ($_POST['skill_description'] ?? ''));
        $proficiency = (string) ($_POST['proficiency'] ?? 'beginner');
        if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 1200 || !in_array($proficiency, ['beginner', 'intermediate', 'advanced'], true)) {
            throw new RuntimeException('Enter a skill name and proficiency level.');
        }

        \db_transaction(function (PDO $pdo) use ($userId, $name, $description, $proficiency): void {
            $pdo->prepare('INSERT IGNORE INTO tbl_skills (name) VALUES (?)')->execute([$name]);
            $stmt = $pdo->prepare('SELECT id FROM tbl_skills WHERE name = ?');
            $stmt->execute([$name]);
            $skillId = (int) $stmt->fetchColumn();
            $pdo->prepare('INSERT INTO tbl_student_skills (student_id, skill_id, proficiency, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE proficiency=VALUES(proficiency), description=VALUES(description)')
                ->execute([$userId, $skillId, $proficiency, $description ?: null]);
        });

        return ['page' => 'biodata', 'notice' => 'Skill saved to your profile and matching profile.'];
    }

    private function studentId(): int
    {
        \require_role('student');

        return (int) \auth_user()['id'];
    }
}
