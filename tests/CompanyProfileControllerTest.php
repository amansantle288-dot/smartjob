<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use SMARTJOB\Modules\Company\Controllers\CompanyController;

$pdo = db();
$token = bin2hex(random_bytes(6));
$userId = 0;
$companyId = 0;

try {
    $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'employer', 'active')")
        ->execute(['SMARTJOB Company Profile Test', 'company-profile-' . $token . '@example.invalid', password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $existingLogoKey = str_repeat('a', 64);
    $pdo->prepare("INSERT INTO tbl_companies (name, logo_key, verification_status) VALUES (?, ?, 'pending')")
        ->execute(['Company Profile Before ' . $token, $existingLogoKey]);
    $companyId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")
        ->execute([$companyId, $userId]);

    $_SESSION['user_id'] = $userId;
    $_POST = [
        'company_name' => 'Company Profile After ' . $token,
        'industry' => 'Information Technology',
        'description' => 'A public description for the company directory.',
        'address' => 'Quezon City, Philippines',
        'website' => 'https://example.invalid',
    ];
    $result = (new CompanyController())->handle('save_company_profile');
    if (($result['page'] ?? '') !== 'company_profile' || ($result['notice'] ?? '') !== 'Company profile saved.') {
        throw new RuntimeException('Unexpected company profile update response.');
    }
    $query = $pdo->prepare('SELECT name, industry, description, address, website, logo_key FROM tbl_companies WHERE id=?');
    $query->execute([$companyId]);
    $company = $query->fetch();
    if (!$company || $company['name'] !== 'Company Profile After ' . $token || $company['industry'] !== 'Information Technology' || $company['description'] !== 'A public description for the company directory.' || $company['address'] !== 'Quezon City, Philippines' || $company['website'] !== 'https://example.invalid' || $company['logo_key'] !== $existingLogoKey) {
        throw new RuntimeException('Company profile details were not saved or the existing logo was changed.');
    }

    $pdo->prepare("INSERT INTO tbl_company_verifications (company_id, status, reviewer_notes, reviewed_at) VALUES (?, 'revision_requested', 'Add a website.', NOW())")
        ->execute([$companyId]);
    $_POST['description'] = 'Updated company profile resubmitted for review.';
    $result = (new CompanyController())->handle('save_company_profile');
    if (($result['notice'] ?? '') !== 'Company profile saved and resubmitted for verification.') {
        throw new RuntimeException('A revised company profile was not resubmitted.');
    }
    $verification = $pdo->prepare('SELECT status, reviewer_notes, reviewed_at FROM tbl_company_verifications WHERE company_id=?');
    $verification->execute([$companyId]);
    $verificationRecord = $verification->fetch();
    $companyStatus = $pdo->prepare('SELECT verification_status FROM tbl_companies WHERE id=?');
    $companyStatus->execute([$companyId]);
    if (!$verificationRecord || $verificationRecord['status'] !== 'pending' || $verificationRecord['reviewer_notes'] !== null || $verificationRecord['reviewed_at'] !== null || $companyStatus->fetchColumn() !== 'pending') {
        throw new RuntimeException('Company verification state was not reset for the resubmission.');
    }

    ob_start();
    render_page('company_profile', null, []);
    $companyProfileHtml = (string) ob_get_clean();
    if (!str_contains($companyProfileHtml, 'name="company_logo"') || !str_contains($companyProfileHtml, 'Company logo')) {
        throw new RuntimeException('The company logo upload is missing from Company Profile.');
    }

    ob_start();
    render_page('internships', null, []);
    $internshipsHtml = (string) ob_get_clean();
    if (str_contains($internshipsHtml, 'name="company_logo"') || str_contains($internshipsHtml, 'Company logo')) {
        throw new RuntimeException('The internship management page still contains company logo upload controls.');
    }
} finally {
    unset($_SESSION['user_id']);
    $_POST = [];
    if ($userId > 0) {
        $pdo->prepare('DELETE FROM tbl_audit_logs WHERE user_id=?')->execute([$userId]);
    }
    if ($companyId > 0) {
        $pdo->prepare('DELETE FROM tbl_companies WHERE id=?')->execute([$companyId]);
    }
    if ($userId > 0) {
        $pdo->prepare('DELETE FROM tbl_users WHERE id=?')->execute([$userId]);
    }
}

echo "Employer company profile editing and persistence verified.\n";
