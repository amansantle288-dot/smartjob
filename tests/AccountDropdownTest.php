<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$pdo = db();
$token = bin2hex(random_bytes(6));
$userIds = [];
$companyIds = [];
$previousUserId = $_SESSION['user_id'] ?? null;
$roleCases = [
    'student' => ['SMARTJOB Student Dropdown', 'Student account'],
    'employer' => ['SMARTJOB Verified Dropdown', 'Company account'],
    'coordinator' => ['SMARTJOB Coordinator Dropdown', 'OJT coordinator'],
    'admin' => ['SMARTJOB Admin Dropdown', 'Administrator'],
];

$pdo->beginTransaction();
try {
    foreach ($roleCases as $role => [$name, $roleLabel]) {
        $email = 'account-dropdown-' . $role . '-' . $token . '@example.invalid';
        $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, ?, 'active', NOW())")
            ->execute([$name, $email, password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT), $role]);
        $userIds[$role] = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('INSERT INTO tbl_student_profiles (user_id) VALUES (?)')->execute([$userIds['student']]);

    foreach (['approved', 'pending'] as $verificationStatus) {
        $suffix = $verificationStatus === 'approved' ? 'verified' : 'unverified';
        $email = 'account-dropdown-employer-' . $suffix . '-' . $token . '@example.invalid';
        $pdo->prepare("INSERT INTO tbl_users (full_name, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, 'employer', 'active', NOW())")
            ->execute(['SMARTJOB ' . ucfirst($suffix) . ' Employer', $email, password_hash('SMARTJOB-test-password', PASSWORD_DEFAULT)]);
        $employerId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO tbl_companies (name, company_type, verification_status) VALUES (?, \'business\', ?)')
            ->execute(['SMARTJOB ' . ucfirst($suffix) . ' Dropdown Company ' . $token, $verificationStatus]);
        $companyId = (int) $pdo->lastInsertId();
        $companyIds[] = $companyId;
        $pdo->prepare("INSERT INTO tbl_company_members (company_id, user_id, member_role, status) VALUES (?, ?, 'owner', 'active')")
            ->execute([$companyId, $employerId]);
        $roleCases['employer-' . $suffix] = [$employerId, $verificationStatus];
    }

    foreach ($roleCases as $role => $case) {
        if (str_starts_with($role, 'employer-')) {
            [$userId, $verificationStatus] = $case;
            $name = $verificationStatus === 'approved' ? 'SMARTJOB Verified Employer' : 'SMARTJOB Unverified Employer';
            $roleLabel = 'Company account';
        } else {
            [$name, $roleLabel] = $case;
            $userId = $userIds[$role];
        }
        $_SESSION['user_id'] = $userId;
        ob_start();
        render_page('account_settings', null, []);
        $html = (string) ob_get_clean();
        $menuStart = strpos($html, '<details class="nav-account"');
        $menuEnd = $menuStart === false ? false : strpos($html, '</details>', $menuStart);
        if ($menuStart === false || $menuEnd === false) {
            throw new RuntimeException('The shared account dropdown was not rendered for role ' . $role . '.');
        }
        $menu = substr($html, $menuStart, $menuEnd - $menuStart + strlen('</details>'));
        $accountLinks = $role === 'admin' ? ['Profile', 'Settings'] : ['Account Settings', 'Change Password'];
        foreach ([
            $name,
            $roleLabel,
            ...$accountLinks,
            'Notifications',
            'bi-box-arrow-right',
            'name="_csrf"',
            'aria-current="page"',
        ] as $expected) {
            if (!str_contains($menu, $expected)) {
                throw new RuntimeException('The account dropdown for ' . $role . ' is missing expected content: ' . $expected);
            }
        }
        if ($role === 'student') {
            foreach (['My Profile', 'Edit Profile', 'My e-Biodata', 'Educational Background', 'My Resume &amp; Credentials', 'My Applications', 'Saved Internships', 'Interested Companies'] as $expected) {
                if (!str_contains($menu, $expected)) {
                    throw new RuntimeException('The student dropdown is missing: ' . $expected);
                }
            }
            if (!str_contains($menu, '>SD</span>')) {
                throw new RuntimeException('The student avatar did not use the account name initials.');
            }
        } elseif ($role === 'employer-verified') {
            foreach (['Company Profile', 'Edit Company Profile', 'Company Verification', 'My Internship Opportunities', 'Applicant Management', 'Messages', 'Email / Inbox'] as $expected) {
                if (!str_contains($menu, $expected)) {
                    throw new RuntimeException('The verified employer dropdown is missing: ' . $expected);
                }
            }
            if (!str_contains($menu, 'href="index.php?page=company_profile"')) {
                throw new RuntimeException('The employer company profile menu item does not open the separate profile page.');
            }
            if (!str_contains($menu, 'Company verified')) {
                throw new RuntimeException('Verified company status was not shown in the employer dropdown.');
            }
        } elseif ($role === 'employer-unverified') {
            foreach (['Company Profile', 'Company Verification', 'Account Settings'] as $expected) {
                if (!str_contains($menu, $expected)) {
                    throw new RuntimeException('The unverified employer dropdown is missing permitted content: ' . $expected);
                }
            }
            foreach (['My Internship Opportunities', 'Applicant Management', 'Messages', 'Email / Inbox'] as $restrictedItem) {
                if (str_contains($menu, $restrictedItem)) {
                    throw new RuntimeException('The unverified employer dropdown exposed restricted content: ' . $restrictedItem);
                }
            }
            if (!str_contains($menu, 'Verification pending')) {
                throw new RuntimeException('Pending company status was not shown in the employer dropdown.');
            }
        } elseif ($role === 'coordinator') {
            foreach (['Student Placement Monitoring', 'OJT Progress &amp; Training', 'Placement Approvals', 'Messages', 'Email / Inbox'] as $expected) {
                if (!str_contains($menu, $expected)) {
                    throw new RuntimeException('The coordinator dropdown is missing: ' . $expected);
                }
            }
            if (str_contains($menu, 'User Management') || str_contains($menu, 'Reports')) {
                throw new RuntimeException('The coordinator dropdown contains an unimplemented destination.');
            }
        } elseif ($role === 'admin') {
            foreach (['Company Verification', 'Verification Settings', 'Messages', 'Email / Inbox'] as $expected) {
                if (!str_contains($menu, $expected)) {
                    throw new RuntimeException('The administrator dropdown is missing: ' . $expected);
                }
            }
            if (str_contains($menu, 'User Management') || str_contains($menu, 'Audit Logs') || str_contains($menu, 'System Settings')) {
                throw new RuntimeException('The administrator dropdown contains an unimplemented destination.');
            }
        }
        if (!str_contains($menu, 'method="post"') || !str_contains($menu, 'value="logout"')) {
            throw new RuntimeException('The logout action no longer uses the protected POST route.');
        }
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($previousUserId === null) {
        unset($_SESSION['user_id']);
    } else {
        $_SESSION['user_id'] = $previousUserId;
    }
}

echo "Shared account dropdown rendering, role visibility, verification status, avatar initials, and CSRF logout verified.\n";
