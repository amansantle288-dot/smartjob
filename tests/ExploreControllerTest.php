<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Company\Controllers\ExploreController;

$pdo = db();
$token = bin2hex(random_bytes(6));
$userId = 0;
$studentId = 0;
$companyId = 0;
$opportunityId = 0;
$skillName = 'Explore Test Skill ' . $token;

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')")
        ->execute(['SMARTJOB Explore Test', 'explore-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Explore Student', 'explore-student-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $studentId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_companies (name, description, industry, address, verification_status) VALUES (?, ?, ?, ?, 'approved')")
        ->execute(['Explore Test Company ' . $token, 'A public company description.', 'Software', 'Test City, Philippines']);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, category, description, requirements, location, work_mode, slots, application_deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'hybrid', 2, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'published')")
        ->execute([$companyId, $userId, 'Explore Test Internship', 'Web Development', 'Public internship description.', 'Public application details.', 'Test City']);
    $opportunityId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_skills (name) VALUES (?)')->execute([$skillName]);
    $skillId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_internship_skills (internship_id, skill_id, is_required) VALUES (?, ?, 1)')
        ->execute([$opportunityId, $skillId]);

    unset($_SESSION['user_id']);
    $_GET = [
        'q' => 'Explore Test Company ' . $token,
        'industry' => 'Software',
        'location' => 'Test City',
        'category' => 'Web Development',
        'skill' => $skillName,
    ];
    $controller = new ExploreController();
    $directory = $controller->viewData('explore');
    if (count($directory['companies']) !== 1 || (int) $directory['companies'][0]['id'] !== $companyId || array_key_exists('email', $directory['companies'][0])) {
        throw new RuntimeException('Guest Explore filters failed or exposed private account information.');
    }

    $_GET = ['company_id' => $companyId];
    $profile = $controller->viewData('explore');
    if (($profile['selectedCompany']['name'] ?? '') !== 'Explore Test Company ' . $token || count($profile['opportunities']) !== 1 || ($profile['opportunities'][0]['requirements'] ?? '') !== 'Public application details.' || ($profile['opportunities'][0]['hiring_status']['state'] ?? '') !== 'open' || (int) ($profile['opportunities'][0]['hiring_duration_days'] ?? 0) !== 30 || empty($profile['opportunities'][0]['posted_date'])) {
        throw new RuntimeException('Public company profile or opportunity details were not returned.');
    }
    $pdo->prepare('INSERT INTO tbl_saved_companies (student_id, company_id) VALUES (?, ?)')->execute([$studentId, $companyId]);
    $_SESSION['user_id'] = $studentId;
    $studentProfile = $controller->viewData('explore');
    if (empty($studentProfile['selectedCompany']['is_saved'])) {
        throw new RuntimeException('Explore did not reflect the student\'s saved company state.');
    }
    ob_start();
    render_page('explore', null, [], $studentProfile);
    $exploreHtml = ob_get_clean();
    if (strpos($exploreHtml, 'Company saved') === false || strpos($exploreHtml, 'Apply now') === false || strpos($exploreHtml, 'Date posted') === false || strpos($exploreHtml, 'Hiring status') === false) {
        throw new RuntimeException('Explore did not render company save and apply actions.');
    }
    ob_start();
    render_page('saved_companies', null, []);
    $savedCompaniesHtml = ob_get_clean();
    if (strpos($savedCompaniesHtml, 'Interested companies') === false || strpos($savedCompaniesHtml, 'View company') === false) {
        throw new RuntimeException('Interested companies page did not render the saved profile.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_GET = [];
    if ($opportunityId > 0) {
        $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id=?')->execute([$opportunityId]);
    }
    if ($companyId > 0) {
        $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
    }
    if ($skillName !== '') {
        $pdo->prepare('DELETE FROM tbl_skills WHERE name=?')->execute([$skillName]);
    }
    foreach ([$userId, $studentId] as $deleteUserId) {
        if ($deleteUserId > 0) {
            $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$deleteUserId]);
        }
    }
}

echo "Public company and opportunity browsing/filtering verified.\n";
