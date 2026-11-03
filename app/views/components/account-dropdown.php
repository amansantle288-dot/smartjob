<?php
$accountRoleLabels = [
    'student' => 'Student account',
    'employer' => 'Company account',
    'coordinator' => 'OJT coordinator',
    'admin' => 'Administrator',
];
$accountNameParts = preg_split('/\s+/', trim((string) $user['full_name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$accountInitials = mb_strtoupper(
    mb_substr($accountNameParts[0] ?? '?', 0, 1)
    . (count($accountNameParts) > 1 ? mb_substr($accountNameParts[count($accountNameParts) - 1], 0, 1) : '')
);
$accountPhotoUrl = null;
$accountPhotoAlt = '';
if ($user['role'] === 'student') {
    $accountPhotoQuery = db()->prepare('SELECT profile_photo_key FROM tbl_student_profiles WHERE user_id=?');
    $accountPhotoQuery->execute([(int) $user['id']]);
    $accountPhotoKey = $accountPhotoQuery->fetchColumn();
    if (is_string($accountPhotoKey) && $accountPhotoKey !== '') {
        $accountPhotoPath = ProfilePhotoStorage::path($accountPhotoKey);
        if (is_file($accountPhotoPath)) {
            $accountPhotoUrl = 'index.php?page=profile_photo';
            $accountPhotoAlt = $user['full_name'] . ' profile photo';
        }
    }
} elseif ($user['role'] === 'employer' && $employerIsApproved && !empty($employerCompany['logo_key'])) {
    $accountPhotoUrl = 'index.php?page=company_logo&id=' . (int) $employerCompany['id'];
    $accountPhotoAlt = ($employerCompany['name'] ?? 'Company') . ' logo';
}
$accountVerificationLabel = 'Active account';
$accountVerificationClass = 'active';
if ($user['role'] === 'employer') {
    $companyStatus = $employerCompany['verification_status'] ?? '';
    $accountVerificationLabel = match ($companyStatus) {
        'approved' => 'Company verified',
        'rejected' => 'Verification update needed',
        default => 'Verification pending',
    };
    $accountVerificationClass = $companyStatus === 'approved' ? 'verified' : 'pending';
} elseif ((bool) ($user['verification_required'] ?? false) || (bool) ($user['phone_verification_required'] ?? false)) {
    $emailVerified = !(bool) $user['verification_required'] || !empty($user['email_verified_at']);
    $phoneVerified = !(bool) $user['phone_verification_required'] || !empty($user['phone_verified_at']);
    $isVerified = $emailVerified && $phoneVerified;
    $accountVerificationLabel = $isVerified ? 'Identity verified' : 'Verification needed';
    $accountVerificationClass = $isVerified ? 'verified' : 'pending';
} elseif (!empty($user['email_verified_at'])) {
    $accountVerificationLabel = 'Email verified';
    $accountVerificationClass = 'verified';
}

$accountMenuGroups = [];
if ($user['role'] === 'student') {
    $accountMenuGroups['My profile'] = [
        ['label' => 'My Profile', 'icon' => 'bi-person-circle', 'page' => 'profile'],
        ['label' => 'Edit Profile', 'icon' => 'bi-pencil-square', 'page' => 'profile', 'fragment' => 'profile-editor'],
        ['label' => 'My e-Biodata', 'icon' => 'bi-file-earmark-person', 'page' => 'biodata'],
        ['label' => 'Educational Background', 'icon' => 'bi-mortarboard', 'page' => 'biodata', 'fragment' => 'education-background'],
        ['label' => 'My Resume & Credentials', 'icon' => 'bi-file-earmark-text', 'page' => 'credentials'],
        ['label' => 'My Applications', 'icon' => 'bi-briefcase', 'page' => 'applications'],
        ['label' => 'Saved Internships', 'icon' => 'bi-bookmark', 'page' => 'saved'],
        ['label' => 'Interested Companies', 'icon' => 'bi-buildings', 'page' => 'saved_companies'],
    ];
} elseif ($user['role'] === 'employer') {
    $accountMenuGroups['Company'] = [
        ['label' => 'Company Profile', 'icon' => 'bi-building', 'page' => 'company_profile'],
        ['label' => 'Edit Company Profile', 'icon' => 'bi-pencil-square', 'page' => 'company_profile', 'fragment' => 'company-profile'],
        ['label' => 'Company Verification', 'icon' => 'bi-patch-check', 'page' => 'company_verification'],
    ];
    if ($employerIsApproved) {
        $accountMenuGroups['Work'] = [
        ['label' => 'My Internship Opportunities', 'icon' => 'bi-briefcase', 'page' => 'internships', 'fragment' => 'opportunities'],
            ['label' => 'Applicant Management', 'icon' => 'bi-people', 'page' => 'applications'],
        ];
    }
} elseif ($user['role'] === 'coordinator') {
    $accountMenuGroups['Placement management'] = [
        ['label' => 'Student Placement Monitoring', 'icon' => 'bi-people', 'page' => 'placements'],
        ['label' => 'OJT Progress & Training', 'icon' => 'bi-journal-check', 'page' => 'training'],
        ['label' => 'Placement Approvals', 'icon' => 'bi-clipboard-check', 'page' => 'placements', 'fragment' => 'placement-approvals'],
    ];
} elseif ($user['role'] === 'admin') {
    $accountMenuGroups['Administration'] = [
        ['label' => 'Company Verification', 'icon' => 'bi-building-check', 'page' => 'companies'],
        ['label' => 'Verification Settings', 'icon' => 'bi-sliders', 'page' => 'verification_admin'],
    ];
}
$accountMenuGroups['Account'] = $user['role'] === 'admin'
    ? [
        ['label' => 'Profile', 'icon' => 'bi-person-circle', 'page' => 'account_settings', 'fragment' => 'account-profile'],
        ['label' => 'Settings', 'icon' => 'bi-gear', 'page' => 'account_settings', 'fragment' => 'security'],
    ]
    : [
        ['label' => 'Account Settings', 'icon' => 'bi-gear', 'page' => 'account_settings'],
        ['label' => 'Change Password', 'icon' => 'bi-shield-lock', 'page' => 'account_settings', 'fragment' => 'security'],
    ];
$accountCommunicationItems = [];
if ($user['role'] !== 'employer' || $employerIsApproved) {
    $accountCommunicationItems[] = ['label' => 'Messages', 'icon' => 'bi-chat-dots', 'page' => 'messages'];
    $accountCommunicationItems[] = ['label' => 'Email / Inbox', 'icon' => 'bi-envelope', 'page' => 'email'];
}
$accountCommunicationItems[] = ['label' => 'Notifications', 'icon' => 'bi-bell', 'page' => 'notifications'];
?>
<details class="nav-account" data-dropdown-panel>
    <summary aria-label="Open account menu for <?= h($user['full_name']) ?>">
        <?php $accountAvatarSize = 'small'; require __DIR__ . '/account-avatar.php'; ?>
        <span class="account-menu-trigger-name"><?= h($accountNameParts[0] ?? $user['full_name']) ?></span>
        <i class="bi bi-chevron-down account-menu-chevron" aria-hidden="true"></i>
    </summary>
    <div class="nav-menu" aria-label="Account menu">
        <header class="account-menu-header">
            <?php $accountAvatarSize = 'large'; require __DIR__ . '/account-avatar.php'; ?>
            <div class="account-menu-identity">
                <strong><?= h($user['full_name']) ?></strong>
                <a href="mailto:<?= h($user['email']) ?>"><?= h($user['email']) ?></a>
                <span class="account-role-label"><?= h($accountRoleLabels[$user['role']] ?? 'SMARTJOB account') ?></span>
                <span class="account-verification-status <?= h($accountVerificationClass) ?>"><i class="bi <?= $accountVerificationClass === 'verified' ? 'bi-patch-check-fill' : 'bi-shield-check' ?>" aria-hidden="true"></i><?= h($accountVerificationLabel) ?></span>
            </div>
        </header>
        <?php foreach ($accountMenuGroups as $groupLabel => $items): ?>
            <section class="account-menu-group" aria-label="<?= h($groupLabel) ?>">
                <h2><?= h($groupLabel) ?></h2>
                <?php foreach ($items as $item): ?>
                    <?php
                    $isActive = $page === $item['page'] && (
                        empty($item['fragment'])
                        || ($user['role'] === 'admin' && $item['label'] === 'Profile')
                    );
                    $href = 'index.php?page=' . rawurlencode($item['page']);
                    if (!empty($item['fragment'])) {
                        $href .= '#' . rawurlencode($item['fragment']);
                    }
                    ?>
                    <a class="account-menu-item<?= $isActive ? ' is-active' : '' ?>" href="<?= h($href) ?>" data-account-page="<?= h($item['page']) ?>" data-account-fragment="<?= h($item['fragment'] ?? '') ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= h($item['icon']) ?>" aria-hidden="true"></i><span><?= h($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <section class="account-menu-group account-menu-communication" aria-label="Communication">
            <h2>Communication</h2>
            <?php foreach ($accountCommunicationItems as $item): ?>
                <?php $isActive = $page === $item['page']; ?>
                <a class="account-menu-item<?= $isActive ? ' is-active' : '' ?>" href="index.php?page=<?= h(rawurlencode($item['page'])) ?>" data-account-page="<?= h($item['page']) ?>" data-account-fragment="" <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= h($item['icon']) ?>" aria-hidden="true"></i><span><?= h($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </section>
        <form class="account-menu-logout" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="logout">
            <button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Log Out</span></button>
        </form>
    </div>
</details>
