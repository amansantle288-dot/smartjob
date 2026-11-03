<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Company\Controllers\ExploreController;

$pdo = db();
$token = bin2hex(random_bytes(6));
$studentId = 0;
$employerId = 0;
$companyId = 0;
$opportunityId = 0;
$skillName = 'Digital Analytics ' . $token;

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active')")
        ->execute(['SMARTJOB Explore Student', 'explore-student-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $studentId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_student_profiles (user_id, program, bio, preferred_location, preferred_work_mode) VALUES (?, 'Information Technology', 'Interested in analytics', 'Test City', 'remote')")
        ->execute([$studentId]);
    $pdo->prepare("INSERT INTO tbl_student_educational_attainment (user_id, tertiary_course) VALUES (?, 'Information Technology')")
        ->execute([$studentId]);

    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')")
        ->execute(['SMARTJOB Explore Employer', 'explore-employer-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $employerId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_companies (name, industry, address, description, verification_status) VALUES (?, 'Software', 'Test City', 'Internship team', 'approved')")
        ->execute(['Recommended Company ' . $token]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_internship_opportunities (company_id, created_by, title, category, description, requirements, work_mode, slots, application_deadline, status) VALUES (?, ?, 'Analytics Intern', 'Data analytics', 'Information Technology opportunity', 'Digital Analytics required', 'remote', 1, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'published')")
        ->execute([$companyId, $employerId]);
    $opportunityId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_skills (name) VALUES (?)')->execute([$skillName]);
    $skillId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tbl_student_skills (student_id, skill_id, proficiency) VALUES (?, ?, ?)')
        ->execute([$studentId, $skillId, 'intermediate']);
    $pdo->prepare('INSERT INTO tbl_internship_skills (internship_id, skill_id, is_required) VALUES (?, ?, 1)')
        ->execute([$opportunityId, $skillId]);

    $_SESSION['user_id'] = $studentId;
    $data = (new ExploreController())->viewData('explore');
    if ((int) ($data['suggestions'][0]['id'] ?? 0) !== $companyId) {
        throw new RuntimeException('Explore suggestions did not use the student profile, skills, location, and work-mode preferences.');
    }
} finally {
    unset($_SESSION['user_id']);
    if ($opportunityId > 0) {
        $pdo->prepare('DELETE FROM tbl_internship_opportunities WHERE id=?')->execute([$opportunityId]);
    }
    if ($companyId > 0) {
        $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
    }
    if ($studentId > 0) {
        $pdo->prepare('DELETE FROM tbl_student_skills WHERE student_id=?')->execute([$studentId]);
    }
    if ($skillName !== '') {
        $pdo->prepare('DELETE FROM tbl_skills WHERE name=?')->execute([$skillName]);
    }
    if ($studentId > 0) {
        $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$studentId]);
    }
    if ($employerId > 0) {
        $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$employerId]);
    }
}

echo "Profile-informed Explore recommendations verified.\n";
