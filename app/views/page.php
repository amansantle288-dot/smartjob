<?php
$user = auth_user();
$employerIsApproved = false;
$employerCompany = null;
if ($user && $user['role'] === 'employer') {
    $companyStatusQuery = db()->prepare("SELECT c.id, c.name, c.logo_key, c.verification_status FROM tbl_companies c JOIN tbl_company_members cm ON cm.company_id=c.id WHERE cm.user_id=? AND cm.status='active' ORDER BY cm.id LIMIT 1");
    $companyStatusQuery->execute([(int) $user['id']]);
    $employerCompany = $companyStatusQuery->fetch() ?: null;
    $employerIsApproved = ($employerCompany['verification_status'] ?? '') === 'approved';
}
$title = 'SMARTJOB';
$pageTitles = [
    'home' => 'Find your next opportunity',
    'login' => 'Sign in',
    'register' => 'Create an account',
    'dashboard' => 'Your dashboard',
    'profile' => 'Student profile',
    'internships' => 'Internship opportunities',
    'company_profile' => 'Company profile',
    'explore' => 'Explore companies',
    'account_settings' => 'Account settings',
    'applications' => 'Applications',
    'saved' => 'Saved internships',
    'saved_companies' => 'Interested companies',
    'notifications' => 'Notifications',
    'placements' => 'Placement approvals',
    'companies' => 'Company verification',
    'admin_companies' => 'Company Account Monitoring',
    'admin_users' => 'User Management',
    'admin_audit' => 'System Audit Logs',
    'admin_notifications' => 'Company Notification Center',
    'company_verification' => 'Company verification',
    'setup' => 'Initial administrator setup',
    'biodata' => 'e-Biodata',
    'credentials' => 'Student credentials',
    'training' => 'Training & certifications',
    'messages' => 'SMARTMESSAGE',
    'email' => 'SMARTMAIL',
    'applicant_biodata' => 'Applicant e-Biodata',
    'verify_email' => 'Verify your email',
    'verify_phone' => 'Verify your mobile',
    'verification_success' => 'Verification complete',
    'verification_admin' => 'Verification provider settings',
];
$title = $pageTitles[$page] ?? 'SMARTJOB';
$adminCount = null;
if (!$user && in_array($page, ['home', 'login', 'setup'], true)) {
    try {
        $adminCount = count_query("SELECT COUNT(*) FROM tbl_users WHERE role = 'admin'");
    } catch (Throwable) {
        $adminCount = null;
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SMARTJOB connects students, employers, and OJT coordinators through a trusted internship placement workflow.">
    <title><?= h($title) ?> · SMARTJOB</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/assets/css/app.css') ?>">
    <?php if (in_array($page, ['verify_email', 'verify_phone', 'verification_success', 'verification_admin'], true)): ?><link rel="stylesheet" href="assets/css/verification.css"><?php endif; ?>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-mark">S</span><span>SMART<span class="brand-accent">JOB</span><small>OJT placement platform</small></span></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation">Menu</button>
    <nav id="main-navigation" aria-label="Main navigation">
        <?php if ($user): ?>
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" <?= $page === 'dashboard' ? 'aria-current="page"' : '' ?> href="index.php?page=dashboard">Dashboard</a>
            <?php if ($user['role'] === 'student'): ?><a class="<?= $page === 'explore' ? 'active' : '' ?>" <?= $page === 'explore' ? 'aria-current="page"' : '' ?> href="index.php?page=explore">Explore</a><a class="<?= $page === 'saved' ? 'active' : '' ?>" <?= $page === 'saved' ? 'aria-current="page"' : '' ?> href="index.php?page=saved">Saved</a><a class="<?= $page === 'saved_companies' ? 'active' : '' ?>" <?= $page === 'saved_companies' ? 'aria-current="page"' : '' ?> href="index.php?page=saved_companies">Companies</a><a class="<?= $page === 'applications' ? 'active' : '' ?> nav-icon-link" <?= $page === 'applications' ? 'aria-current="page"' : '' ?> href="index.php?page=applications" aria-label="My applications" title="My applications"><i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i></a><?php endif; ?>
            <?php if ($user['role'] === 'employer'): ?><a class="<?= $page === 'internships' ? 'active' : '' ?>" <?= $page === 'internships' ? 'aria-current="page"' : '' ?> href="index.php?page=internships">Opportunities</a><a class="<?= $page === 'company_verification' ? 'active' : '' ?>" <?= $page === 'company_verification' ? 'aria-current="page"' : '' ?> href="index.php?page=company_verification">Verification</a><?php if ($employerIsApproved): ?><a class="<?= $page === 'applications' ? 'active' : '' ?> nav-icon-link" <?= $page === 'applications' ? 'aria-current="page"' : '' ?> href="index.php?page=applications" aria-label="Applicants" title="Applicants"><i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i></a><?php endif; ?><?php endif; ?>
            <?php if ($user['role'] === 'coordinator'): ?><a class="<?= $page === 'training' ? 'active' : '' ?>" <?= $page === 'training' ? 'aria-current="page"' : '' ?> href="index.php?page=training">Training reviews</a><a class="<?= $page === 'placements' ? 'active' : '' ?>" <?= $page === 'placements' ? 'aria-current="page"' : '' ?> href="index.php?page=placements">Placements</a><?php endif; ?>
            <?php if ($user['role'] === 'admin'): ?>
                <details class="admin-nav" data-dropdown-panel>
                    <summary class="<?= in_array($page, ['admin_companies','companies','admin_users','admin_audit','admin_notifications','verification_admin'], true) ? 'active' : '' ?>">Administration</summary>
                    <div class="admin-nav-menu" aria-label="Administrator navigation">
                        <strong>Company management</strong>
                        <a href="index.php?page=admin_companies">Company Account Monitoring</a>
                        <a href="index.php?page=admin_companies&amp;new=1">New Registrations</a>
                        <a href="index.php?page=companies">Verification Requests</a>
                        <a href="index.php?page=admin_notifications">Company Notification Center</a>
                        <strong>User management</strong>
                        <a href="index.php?page=admin_users">All Users</a>
                        <a href="index.php?page=admin_users&amp;role=student">Student Accounts</a>
                        <a href="index.php?page=admin_users&amp;role=employer">Company Accounts</a>
                        <strong>System administration</strong>
                        <a href="index.php?page=admin_audit">System Audit Logs</a>
                        <a href="index.php?page=admin_audit&amp;action_filter=auth.login.failed">Security Events</a>
                        <a href="index.php?page=admin_notifications#history">Notification History</a>
                    </div>
                </details>
            <?php endif; ?>
            <details class="nav-activity" data-activity="notifications" data-dropdown-panel>
                <summary aria-label="Notifications"><i class="bi bi-bell-fill" aria-hidden="true"></i><span class="activity-count" hidden></span></summary>
                <div class="activity-menu"><div class="activity-menu-heading"><strong>Notifications</strong><a href="index.php?page=notifications">View all</a></div><div class="activity-items" data-activity-items><p class="muted">Loading notifications…</p></div><button class="text-button" type="button" data-mark-all hidden>Mark all as read</button></div>
            </details>
            <?php if ($user['role'] !== 'employer' || $employerIsApproved): ?><details class="nav-activity" data-activity="messages" data-dropdown-panel>
                <summary aria-label="Messages"><i class="bi bi-chat-dots-fill" aria-hidden="true"></i><span class="activity-count" hidden></span></summary>
                <div class="activity-menu"><div class="activity-menu-heading"><strong>Recent conversations</strong><a href="index.php?page=messages">Open messages</a></div><div class="activity-items" data-activity-items><p class="muted">Loading conversations…</p></div></div>
            </details><?php endif; ?>
            <?php require __DIR__ . '/components/account-dropdown.php'; ?>
        <?php else: ?>
            <a class="<?= $page === 'home' ? 'active' : '' ?>" <?= $page === 'home' ? 'aria-current="page"' : '' ?> href="index.php">Home</a><a class="<?= $page === 'explore' ? 'active' : '' ?>" <?= $page === 'explore' ? 'aria-current="page"' : '' ?> href="index.php?page=explore">Explore companies</a><a href="index.php#about">About</a><a class="<?= $page === 'login' ? 'active' : '' ?>" <?= $page === 'login' ? 'aria-current="page"' : '' ?> href="index.php?page=login">Log in</a><a class="button button-small" href="index.php?page=register">Sign up</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container<?= $page === 'companies' ? ' verification-admin-container' : '' ?><?= $page === 'biodata' ? ' education-page-container' : '' ?><?= in_array($page, ['dashboard','admin_companies','admin_users','admin_audit','admin_notifications'], true) && ($user['role'] ?? '') === 'admin' ? ' admin-workspace' : '' ?>">
    <?php if ($notice): ?><div class="notice success" role="status"><?= h($notice) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endforeach; ?>

    <?php if ($page === 'home'): ?>
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</p>
                <h1>Make your training<br><span>count.</span></h1>
                <p class="hero-lead">A clearer path from classroom skills to meaningful on-the-job training. Discover opportunities, apply with confidence, and keep every placement in view.</p>
                <div class="hero-actions"><a class="button" href="index.php?page=register">Create your account <span aria-hidden="true">→</span></a><a class="button button-quiet" href="index.php?page=explore">Explore companies</a></div>
                <div class="trust-line"><span class="trust-dot"></span> One platform for students, employers, and coordinators</div>
            </div>
            <div class="hero-panel" aria-label="SMARTJOB workflow">
                <div class="panel-top"><span class="panel-label">YOUR PLACEMENT JOURNEY</span><span class="live-pill">CONNECTED</span></div>
                <div class="journey-step"><span class="step-number">01</span><div><b>Build your profile</b><small>Showcase your skills and goals</small></div><span class="step-check">✓</span></div>
                <div class="journey-line"></div>
                <div class="journey-step"><span class="step-number">02</span><div><b>Discover opportunities</b><small>Explore verified employers</small></div><span class="step-check">✓</span></div>
                <div class="journey-line"></div>
                <div class="journey-step"><span class="step-number step-active">03</span><div><b>Start your OJT</b><small>Track your application and placement</small></div><span class="step-arrow">↗</span></div>
                <div class="panel-footer"><span>BUILT FOR YOUR FUTURE</span><span>SMARTJOB · 01</span></div>
            </div>
        </section>
        <section id="about" class="feature-row">
            <article><span class="feature-icon">01</span><h2>Student-first</h2><p>Keep your profile, opportunities, and applications organized in one place.</p></article>
            <article><span class="feature-icon">02</span><h2>Trusted companies</h2><p>Company accounts go through an administrator review before publishing.</p></article>
            <article><span class="feature-icon">03</span><h2>Clear progress</h2><p>Follow application decisions and placement milestones with less guesswork.</p></article>
        </section>
        <?php if (!empty($featuredCompanies)): ?><section class="home-featured"><div class="section-heading"><div><p class="eyebrow">START EXPLORING</p><h2>Companies with open opportunities</h2></div><a class="text-link" href="index.php?page=explore">Explore all companies →</a></div><div class="company-grid"><?php foreach ($featuredCompanies as $company): ?><a class="company-card" href="index.php?page=explore&amp;company_id=<?= (int) $company['id'] ?>"><span class="company-mark" aria-hidden="true"><?php if (!empty($company['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $company['id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($company['name'], 0, 1))) ?><?php endif; ?></span><span class="company-card-copy"><strong><?= h($company['name']) ?></strong><small><?= h($company['industry'] ?: 'Verified company') ?><?= $company['address'] ? ' · ' . h($company['address']) : '' ?></small></span><span class="badge published"><?= (int) $company['opportunity_count'] ?> open</span></a><?php endforeach; ?></div></section><?php endif; ?>
        <?php if ($adminCount === 0): ?><aside class="setup-callout"><span>First time installing SMARTJOB?</span><a href="index.php?page=setup">Create the initial administrator account →</a></aside><?php endif; ?>

    <?php elseif ($page === 'explore'): ?>
        <?php require __DIR__ . '/explore.php'; ?>

    <?php elseif ($page === 'login'): ?>
        <section class="auth-wrap"><div class="auth-aside"><p class="eyebrow">WELCOME BACK</p><h1>Your next step<br>is waiting.</h1><p>Sign in to pick up where you left off.</p></div><div class="card auth-card"><h2>Sign in</h2><p class="muted">Use your SMARTJOB account details.</p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="login"><label>Email address<input name="email" type="email" maxlength="190" autocomplete="email" required></label><label>Password<input name="password" type="password" maxlength="72" autocomplete="current-password" required></label><button class="button full" type="submit">Sign in</button></form><p class="form-foot">New to SMARTJOB? <a href="index.php?page=register">Create an account</a></p></div></section>

    <?php elseif ($page === 'register'): ?>
        <section class="auth-wrap"><div class="auth-aside"><p class="eyebrow">GET STARTED</p><h1>Opportunity<br>meets preparation.</h1><p>Create a secure account to begin your SMARTJOB journey.</p></div><div class="card auth-card"><h2>Create account</h2><p class="muted">Use an email address and mobile number you can access.</p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="register"><label>Full name<input name="full_name" maxlength="120" autocomplete="name" required></label><label>Email address<input name="email" type="email" maxlength="190" autocomplete="email" required></label><label>Mobile number <span class="field-help">Philippines numbers such as +639XXXXXXXXX</span><input name="phone_number" type="tel" maxlength="20" autocomplete="tel" required></label><label>Account type<select name="role" id="account-role"><option value="student">Student</option><option value="employer">Employer / company representative</option></select></label><label id="company-field" hidden>Company name<input name="company_name" maxlength="180"></label><label>Password <span class="field-help">12–72 bytes</span><input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="button full" type="submit">Create account</button></form><p class="form-foot">Already registered? <a href="index.php?page=login">Sign in</a></p></div></section>

    <?php elseif ($page === 'setup'): ?>
        <?php if ($adminCount !== 0): ?><section class="card narrow"><h1>Setup unavailable</h1><p>An administrator account already exists.</p><a href="index.php?page=login">Sign in</a></section><?php else: ?><section class="card narrow"><p class="eyebrow">ONE-TIME SETUP</p><h1>Create administrator</h1><p class="muted">This form is available only until the first administrator is created. Use a unique password and keep it private.</p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="setup_admin"><label>Full name<input name="full_name" maxlength="120" required></label><label>Email address<input name="email" type="email" maxlength="190" required></label><label>Password <span class="field-help">12–72 bytes</span><input name="password" type="password" minlength="12" maxlength="72" required></label><button class="button" type="submit">Create administrator</button></form></section><?php endif; ?>

    <?php elseif ($page === 'dashboard'): ?>
        <?php if ($user): ?>
            <?php
            $dashboardPhotoAvailable = false;
            if ($user['role'] === 'student') {
                $dashboardPhotoQuery = db()->prepare('SELECT profile_photo_key FROM tbl_student_profiles WHERE user_id=?');
                $dashboardPhotoQuery->execute([(int) $user['id']]);
                $dashboardPhotoKey = $dashboardPhotoQuery->fetchColumn();
                $dashboardPhotoAvailable = is_string($dashboardPhotoKey)
                    && preg_match('/\A[a-f0-9]{64}\z/', $dashboardPhotoKey) === 1
                    && is_file(ProfilePhotoStorage::path($dashboardPhotoKey));
            }
            ?>
            <section class="page-heading dashboard-heading"><div><?php if ($user['role'] === 'student'): ?><div class="avatar-frame avatar-small"><?php if ($dashboardPhotoAvailable): ?><img src="index.php?page=profile_photo" alt="<?= h($user['full_name']) ?> profile photo"><?php else: ?><span aria-hidden="true"><?= h(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span><?php endif; ?></div><?php endif; ?><p class="eyebrow"><?= h(strtoupper($user['role'])) ?> WORKSPACE</p><h1><?= $user['role'] === 'admin' ? 'Administrator dashboard' : 'Good to see you, ' . h(explode(' ', $user['full_name'])[0]) . '.' ?></h1><p class="muted"><?= $user['role'] === 'admin' ? 'Monitor accounts, company verification, and system security.' : 'Here’s your SMARTJOB overview.' ?></p></div></section>
        <?php if ($user['role'] === 'student'): ?>
            <?php $applied = count_query('SELECT COUNT(*) FROM tbl_applications WHERE student_id = ?', [(int) $user['id']]); $open = count_query("SELECT COUNT(*) FROM tbl_internship_opportunities WHERE status = 'published' AND application_deadline >= CURDATE()"); ?>
            <?php if (!empty($user['phone_number']) && empty($user['phone_verified_at'])): ?><div class="notice">Mobile number not verified. <a href="index.php?page=verify_phone">Verify your phone</a></div><?php endif; ?>
            <div class="stat-grid"><div class="stat-card"><span>Open opportunities</span><strong><?= $open ?></strong><a href="index.php?page=explore">Explore companies →</a></div><div class="stat-card"><span>My applications</span><strong><?= $applied ?></strong><a href="index.php?page=applications">Track status →</a></div><div class="stat-card"><span>e-Biodata</span><strong>Build</strong><a href="index.php?page=biodata">Create and preview →</a></div></div>
        <?php elseif ($user['role'] === 'employer'): ?>
            <?php $companyId = current_company_id((int) $user['id']); $companyStmt = db()->prepare('SELECT c.name, c.verification_status, v.status AS review_status, v.reviewer_notes FROM tbl_companies c LEFT JOIN tbl_company_verifications v ON v.company_id=c.id WHERE c.id=?'); $companyStmt->execute([$companyId]); $company = $companyStmt->fetch(); $postCount = count_query('SELECT COUNT(*) FROM tbl_internship_opportunities WHERE company_id = ?', [$companyId]); $applicantCount = ($company['verification_status'] ?? '') === 'approved' ? count_query('SELECT COUNT(*) FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id WHERE i.company_id=?', [$companyId]) : 0; ?>
            <div class="status-banner"><span class="status-dot <?= $company['verification_status'] === 'approved' ? 'green' : '' ?>"></span><div><b><?= h($company['name']) ?></b><span>Verification: <?= h(ucfirst($company['verification_status'])) ?><?= $company['verification_status'] !== 'approved' ? ' · Approval is required before publishing internships.' : '' ?></span><?php if (($company['review_status'] ?? '') === 'revision_requested' && !empty($company['reviewer_notes'])): ?><span class="verification-review-note"><strong>Requested updates:</strong> <?= h($company['reviewer_notes']) ?> Update your profile under Opportunities to resubmit.</span><?php endif; ?></div><a class="button button-small button-quiet" href="index.php?page=company_verification">Manage verification</a></div><div class="stat-grid"><div class="stat-card"><span>Internship posts</span><strong><?= $postCount ?></strong><a href="index.php?page=internships">Manage opportunities →</a></div><?php if ($company['verification_status'] === 'approved'): ?><div class="stat-card"><span>Applications received</span><strong><?= $applicantCount ?></strong><a href="index.php?page=applications">Review applicants →</a></div><?php else: ?><div class="stat-card"><span>Verification documents</span><strong>Continue</strong><a href="index.php?page=company_verification">Upload and track documents →</a></div><?php endif; ?></div>
        <?php elseif ($user['role'] === 'admin'): ?>
            <div class="admin-stat-grid">
                <?php foreach ([
                    ['Total registered users', $adminStats['total_users'], 'bi-people-fill', 'index.php?page=admin_users'],
                    ['Registered students', $adminStats['students'], 'bi-mortarboard-fill', 'index.php?page=admin_users&role=student'],
                    ['Registered companies', $adminStats['companies'], 'bi-building', 'index.php?page=admin_companies'],
                    ['New companies · 7 days', $adminStats['new_companies'], 'bi-person-plus-fill', 'index.php?page=admin_companies&new=1'],
                    ['Pending verification', $adminStats['pending_companies'], 'bi-shield-exclamation', 'index.php?page=companies'],
                    ['Verified companies', $adminStats['verified_companies'], 'bi-patch-check-fill', 'index.php?page=admin_companies&verification=approved'],
                    ['Unread admin notifications', $adminStats['unread_notifications'], 'bi-bell-fill', 'index.php?page=notifications'],
                    ['Security events · 24 hours', $adminStats['security_events'], 'bi-shield-lock-fill', 'index.php?page=admin_audit&action_filter=auth.login.failed'],
                ] as [$statLabel, $statValue, $statIcon, $statLink]): ?>
                    <a class="admin-stat-card" href="<?= h($statLink) ?>"><span class="admin-stat-icon"><i class="bi <?= h($statIcon) ?>" aria-hidden="true"></i></span><span><?= h($statLabel) ?></span><strong><?= (int) $statValue ?></strong></a>
                <?php endforeach; ?>
            </div>
            <div class="admin-dashboard-grid">
                <section class="card admin-panel"><header class="admin-panel-heading"><div><p class="eyebrow">ACTION REQUIRED</p><h2>Pending company verification</h2></div><a class="text-link" href="index.php?page=companies">Review requests →</a></header>
                    <?php if (!$adminPendingCompanies): ?><p class="muted">No companies are waiting for verification.</p><?php else: ?><div class="admin-activity-list"><?php foreach ($adminPendingCompanies as $pendingCompany): ?><article class="admin-activity-item"><span class="admin-activity-icon"><i class="bi bi-building" aria-hidden="true"></i></span><div><strong><?= h($pendingCompany['name']) ?></strong><p><?= h($pendingCompany['owner_email'] ?? 'No owner email') ?> · Registered <?= h(date('M j, Y', strtotime((string) $pendingCompany['created_at']))) ?></p></div><a class="button button-small button-quiet" href="index.php?page=companies&amp;company_id=<?= (int) $pendingCompany['id'] ?>#company-<?= (int) $pendingCompany['id'] ?>">Review</a></article><?php endforeach; ?></div><?php endif; ?>
                </section>
                <section class="card admin-panel"><header class="admin-panel-heading"><div><p class="eyebrow">ACCOUNT & ADMIN EVENTS</p><h2>Recent activity</h2></div><a class="text-link" href="index.php?page=admin_audit">View audit logs →</a></header>
                    <?php if (!$adminRecentActivity): ?><p class="muted">No recent account or administrative events.</p><?php else: ?><div class="admin-activity-list"><?php foreach ($adminRecentActivity as $activity): ?><article class="admin-activity-item"><span class="admin-activity-icon"><i class="bi <?= $activity['result'] === 'failure' ? 'bi-shield-exclamation' : 'bi-clock-history' ?>" aria-hidden="true"></i></span><div><strong><?= h($activity['description'] ?: $activity['action']) ?></strong><p><?= h($activity['actor_name'] ?: 'System') ?> · <?= h(ucfirst((string) $activity['actor_role'])) ?> · <?= h(date('M j, Y g:i A', strtotime((string) $activity['created_at']))) ?></p></div><span class="badge <?= $activity['result'] === 'failure' ? 'rejected' : 'approved' ?>"><?= h(ucfirst((string) $activity['result'])) ?></span></article><?php endforeach; ?></div><?php endif; ?>
                </section>
            </div>
            <section class="admin-quick-links"><a class="button" href="index.php?page=admin_companies">Company Account Monitoring</a><a class="button button-quiet" href="index.php?page=admin_users">User Management</a><a class="button button-quiet" href="index.php?page=admin_notifications">Company Notification Center</a><a class="button button-quiet" href="index.php?page=admin_audit">System Audit Logs</a></section>
        <?php elseif ($user['role'] === 'coordinator'): ?>
            <?php $coordinatorId = (int) $user['id']; $pendingPlacements = count_query("SELECT COUNT(*) FROM tbl_placements p JOIN tbl_applications a ON a.id=p.application_id JOIN tbl_student_profiles sp ON sp.user_id=a.student_id JOIN tbl_ojt_coordinators oc ON oc.institution_id=sp.institution_id WHERE oc.user_id=? AND oc.status='active' AND p.status='pending'", [$coordinatorId]); $activePlacements = count_query("SELECT COUNT(*) FROM tbl_placements p JOIN tbl_applications a ON a.id=p.application_id JOIN tbl_student_profiles sp ON sp.user_id=a.student_id JOIN tbl_ojt_coordinators oc ON oc.institution_id=sp.institution_id WHERE oc.user_id=? AND oc.status='active' AND p.status='active'", [$coordinatorId]); ?>
            <div class="stat-grid"><div class="stat-card"><span>Pending placement approvals</span><strong><?= $pendingPlacements ?></strong><a href="index.php?page=placements">Review placements →</a></div><div class="stat-card"><span>Active placements</span><strong><?= $activePlacements ?></strong><a href="index.php?page=placements">View progress →</a></div><div class="stat-card"><span>Training evidence</span><strong>Review</strong><a href="index.php?page=training">Review training →</a></div></div>
        <?php else: ?><div class="notice">Coordinator access is reserved for administrator-authorized institutional accounts.</div><?php endif; ?><?php endif; ?>

    <?php elseif ($page === 'profile'): ?>
        <?php $stmt = db()->prepare('SELECT institution_id, program, year_level, bio, preferred_location, preferred_work_mode, profile_photo_key FROM tbl_student_profiles WHERE user_id = ?'); $stmt->execute([(int) $user['id']]); $profile = $stmt->fetch() ?: []; $institutions = db()->query("SELECT id, name FROM tbl_institutions WHERE status='active' ORDER BY name")->fetchAll(); ?>
        <section class="page-heading"><p class="eyebrow">YOUR INFORMATION</p><h1>Student profile</h1><p class="muted">Manage your profile and preferences. Add academic history once in <a class="text-link" href="index.php?page=biodata#education-background">Educational Background</a>. <a class="text-link" href="index.php?page=account_settings">Manage account and photo →</a></p></section><div class="profile-photo-card"><?php if (!empty($profile['profile_photo_key'])): ?><img src="index.php?page=profile_photo" alt="<?= h($user['full_name']) ?> profile photo"><?php else: ?><span aria-hidden="true"><?= h(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span><?php endif; ?><a class="text-link" href="index.php?page=account_settings">Update profile photo</a></div>        <form id="profile-editor" class="card form-card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_profile"><label>Educational institution<select name="institution_id"><option value="">Not listed</option><?php foreach ($institutions as $institution): ?><option value="<?= (int) $institution['id'] ?>" <?= (int) ($profile['institution_id'] ?? 0) === (int) $institution['id'] ? 'selected' : '' ?>><?= h($institution['name']) ?></option><?php endforeach; ?></select><span class="field-help">Your coordinator uses this to review placements.</span></label><p class="field-help">School name, degree program, and year level are managed in Educational Background.</p><div class="form-grid"><label>Preferred internship location<input name="preferred_location" maxlength="180" value="<?= h($profile['preferred_location'] ?? '') ?>" placeholder="City or province"></label><label>Preferred work mode<select name="preferred_work_mode"><?php foreach (['any' => 'Any work mode', 'onsite' => 'On-site', 'hybrid' => 'Hybrid', 'remote' => 'Remote'] as $value => $label): ?><option value="<?= h($value) ?>" <?= ($profile['preferred_work_mode'] ?? 'any') === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label></div><label>Short introduction<textarea name="bio" maxlength="2000" rows="5" placeholder="Tell employers about your interests and goals."><?= h($profile['bio'] ?? '') ?></textarea></label><button class="button" type="submit">Save profile</button></form>

    <?php elseif ($page === 'admin_companies'): ?>
        <section class="page-heading"><p class="eyebrow">COMPANY MANAGEMENT</p><h1>Company Account Monitoring</h1><p class="muted">Review registrations, account status, verification progress, and administrative communication.</p></section>
        <form class="card admin-filter-bar" method="get"><input type="hidden" name="page" value="admin_companies"><label>Search company or contact<input type="search" name="q" value="<?= h($adminCompanyFilters['q']) ?>" placeholder="Company, email, or phone"></label><label>Verification<select name="verification"><option value="">All verification states</option><?php foreach (['pending','approved','rejected','suspended'] as $filterStatus): ?><option value="<?= h($filterStatus) ?>" <?= $adminCompanyFilters['verification'] === $filterStatus ? 'selected' : '' ?>><?= h(ucwords($filterStatus)) ?></option><?php endforeach; ?></select></label><label>Account status<select name="status"><option value="">All account states</option><?php foreach (['active','inactive','suspended','pending_verification'] as $filterStatus): ?><option value="<?= h($filterStatus) ?>" <?= $adminCompanyFilters['status'] === $filterStatus ? 'selected' : '' ?>><?= h(ucwords(str_replace('_', ' ', $filterStatus))) ?></option><?php endforeach; ?></select></label><input type="hidden" name="new" value="<?= h($adminCompanyFilters['new']) ?>"><button class="button button-small" type="submit">Search</button><?php if ($adminCompanyFilters['new'] === '1'): ?><a class="button button-small button-quiet" href="index.php?page=admin_companies">All registrations</a><?php endif; ?></form>
        <p class="muted"><?= (int) $adminCompanyTotal ?> company accounts<?= $adminCompanyFilters['new'] === '1' ? ' registered in the last 7 days' : '' ?> · sorted newest first</p>
        <?php if (!$adminCompanies): ?><div class="empty-state">No company accounts match these filters.</div><?php else: ?>
            <div class="table-wrap"><table class="admin-table"><thead><tr><th>Company / contact</th><th>Registered</th><th>Account</th><th>Verification</th><th>Last login</th><th>Notification</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($adminCompanies as $adminCompany): $isNewCompany = strtotime((string) $adminCompany['created_at']) >= time() - 7 * 86400; ?>
                    <tr class="<?= $isNewCompany && $adminCompany['verification_status'] === 'pending' ? 'admin-attention-row' : '' ?>" id="company-<?= (int) $adminCompany['id'] ?>">
                        <td><strong><?= h($adminCompany['name']) ?><?php if ($isNewCompany): ?> <span class="badge pending">New</span><?php endif; ?></strong><small><?= h($adminCompany['email'] ?? 'No registered owner email') ?></small><small><?= h($adminCompany['phone_number'] ?: 'No contact number') ?></small></td>
                        <td><?= h(date('M j, Y', strtotime((string) $adminCompany['created_at']))) ?></td>
                        <td><span class="badge <?= h((string) ($adminCompany['account_status'] ?? 'inactive')) ?>"><?= h(ucwords(str_replace('_', ' ', (string) ($adminCompany['account_status'] ?? 'unavailable')))) ?></span></td>
                        <td><span class="badge <?= h((string) $adminCompany['verification_status']) ?>"><?= h(ucwords(str_replace('_', ' ', (string) $adminCompany['verification_status']))) ?></span><?php if ($adminCompany['review_status'] === 'revision_requested'): ?><small>Requirements requested</small><?php endif; ?></td>
                        <td><?= $adminCompany['last_login_at'] ? h(date('M j, Y g:i A', strtotime((string) $adminCompany['last_login_at']))) : 'Never' ?></td>
                        <td><?= $adminCompany['notification_status'] ? '<span class="badge ' . h((string) $adminCompany['notification_status']) . '">' . h(ucfirst((string) $adminCompany['notification_status'])) . '</span>' : 'Not sent' ?></td>
                        <td class="admin-row-actions"><a class="text-link" href="index.php?page=companies&amp;company_id=<?= (int) $adminCompany['id'] ?>#company-<?= (int) $adminCompany['id'] ?>">Review verification</a><a class="text-link" href="index.php?page=admin_notifications&amp;company_ids%5B%5D=<?= (int) $adminCompany['id'] ?>">Send notification</a><a class="text-link" href="index.php?page=admin_users&amp;q=<?= rawurlencode((string) ($adminCompany['email'] ?? '')) ?>">Manage account</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <?php if ($adminCompanyPageCount > 1): ?><nav class="admin-pagination" aria-label="Company account pages"><?php for ($pageNumber = 1; $pageNumber <= $adminCompanyPageCount; $pageNumber++): ?><a class="<?= $pageNumber === $adminCompanyPage ? 'current' : '' ?>" href="index.php?<?= h(http_build_query(['page' => 'admin_companies', 'p' => $pageNumber, 'q' => $adminCompanyFilters['q'], 'verification' => $adminCompanyFilters['verification'], 'status' => $adminCompanyFilters['status'], 'new' => $adminCompanyFilters['new']])) ?>"><?= $pageNumber ?></a><?php endfor; ?></nav><?php endif; ?>
        <?php endif; ?>

    <?php elseif ($page === 'admin_users'): ?>
        <section class="page-heading"><p class="eyebrow">USER MANAGEMENT</p><h1>All user accounts</h1><p class="muted">Search account records, review security signals, and manage eligible account status. Credentials and authentication tokens are never displayed.</p></section>
        <form class="card admin-filter-bar" method="get"><input type="hidden" name="page" value="admin_users"><label>Search name or email<input type="search" name="q" value="<?= h($adminUserFilters['q']) ?>" placeholder="Name or email address"></label><label>Role<select name="role"><option value="">All roles</option><?php foreach (['student','employer','coordinator','admin'] as $filterRole): ?><option value="<?= h($filterRole) ?>" <?= $adminUserFilters['role'] === $filterRole ? 'selected' : '' ?>><?= h(ucfirst($filterRole)) ?></option><?php endforeach; ?></select></label><label>Account status<select name="status"><option value="">All statuses</option><?php foreach (['active','inactive','suspended','pending_verification'] as $filterStatus): ?><option value="<?= h($filterStatus) ?>" <?= $adminUserFilters['status'] === $filterStatus ? 'selected' : '' ?>><?= h(ucwords(str_replace('_', ' ', $filterStatus))) ?></option><?php endforeach; ?></select></label><button class="button button-small" type="submit">Search</button></form>
        <p class="muted"><?= (int) $adminUserTotal ?> accounts</p>
        <?php if (!$adminUsers): ?><div class="empty-state">No user accounts match these filters.</div><?php else: ?>
            <div class="table-wrap"><table class="admin-table"><thead><tr><th>Account</th><th>Role</th><th>Registered</th><th>Last login</th><th>Failed logins · 30 days</th><th>Status / action</th></tr></thead><tbody>
                <?php foreach ($adminUsers as $adminUser): ?><tr><td><strong><?= h($adminUser['full_name']) ?></strong><small><?= h($adminUser['email']) ?></small></td><td><?= h(ucfirst((string) $adminUser['role'])) ?></td><td><?= h(date('M j, Y', strtotime((string) $adminUser['created_at']))) ?></td><td><?= $adminUser['last_login_at'] ? h(date('M j, Y g:i A', strtotime((string) $adminUser['last_login_at']))) : 'Never' ?></td><td><?php if ((int) $adminUser['failed_logins'] > 0): ?><a href="index.php?page=admin_audit&amp;q=<?= rawurlencode((string) $adminUser['email']) ?>"><?= (int) $adminUser['failed_logins'] ?> events</a><?php else: ?>None<?php endif; ?></td><td><span class="badge <?= h((string) $adminUser['status']) ?>"><?= h(ucwords(str_replace('_', ' ', (string) $adminUser['status']))) ?></span>
                    <?php if ($adminUser['role'] !== 'admin' && (int) $adminUser['id'] !== (int) $user['id']): ?><form method="post" class="admin-status-form" onsubmit="return confirm('Change the status of this account?');"><?= csrf_field() ?><input type="hidden" name="action" value="admin_update_user_status"><input type="hidden" name="user_id" value="<?= (int) $adminUser['id'] ?>"><select name="status" aria-label="New account status"><?php foreach (['active','inactive','suspended'] as $statusOption): ?><option value="<?= h($statusOption) ?>" <?= $adminUser['status'] === $statusOption ? 'selected' : '' ?>><?= h(ucfirst($statusOption)) ?></option><?php endforeach; ?></select><button class="button button-small button-quiet" type="submit">Update</button></form><?php else: ?><small>Protected administrator account</small><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if ($adminUserPageCount > 1): ?><nav class="admin-pagination" aria-label="User account pages"><?php for ($pageNumber = 1; $pageNumber <= $adminUserPageCount; $pageNumber++): ?><a class="<?= $pageNumber === $adminUserPage ? 'current' : '' ?>" href="index.php?<?= h(http_build_query(['page' => 'admin_users', 'p' => $pageNumber, 'q' => $adminUserFilters['q'], 'role' => $adminUserFilters['role'], 'status' => $adminUserFilters['status']])) ?>"><?= $pageNumber ?></a><?php endfor; ?></nav><?php endif; ?>
        <?php endif; ?>

    <?php elseif ($page === 'admin_audit'): ?>
        <section class="page-heading"><p class="eyebrow">SYSTEM ADMINISTRATION</p><h1>System Audit Logs</h1><p class="muted">Read-only record of account and administrative activity. Sensitive credentials and raw network addresses are not included.</p><a class="button button-small" href="index.php?page=admin_audit_export&amp;<?= h(http_build_query($adminAuditFilters)) ?>">Export filtered CSV</a></section>
        <form class="card admin-filter-bar" method="get"><input type="hidden" name="page" value="admin_audit"><label>Search event<input type="search" name="q" value="<?= h($adminAuditFilters['q']) ?>" placeholder="Action, actor, module, description"></label><label>Role<select name="role"><option value="">All roles</option><?php foreach (['student','employer','coordinator','admin','system'] as $filterRole): ?><option value="<?= h($filterRole) ?>" <?= $adminAuditFilters['role'] === $filterRole ? 'selected' : '' ?>><?= h(ucfirst($filterRole)) ?></option><?php endforeach; ?></select></label><label>Result<select name="result"><option value="">All results</option><option value="success" <?= $adminAuditFilters['result'] === 'success' ? 'selected' : '' ?>>Success</option><option value="failure" <?= $adminAuditFilters['result'] === 'failure' ? 'selected' : '' ?>>Failure</option></select></label><label>Action<input name="action_filter" value="<?= h($adminAuditFilters['action_filter']) ?>" placeholder="Exact action (optional)"></label><button class="button button-small" type="submit">Filter logs</button></form>
        <section class="card admin-panel"><header class="admin-panel-heading"><div><p class="eyebrow">SECURITY</p><h2>Recent login attempts</h2></div></header><?php if (!$adminSecurityEvents): ?><p class="muted">No login attempts recorded.</p><?php else: ?><div class="table-wrap"><table class="admin-table"><thead><tr><th>Time</th><th>Account identifier</th><th>Result</th></tr></thead><tbody><?php foreach ($adminSecurityEvents as $securityEvent): ?><tr><td><?= h(date('M j, Y g:i A', strtotime((string) $securityEvent['attempted_at']))) ?></td><td><?= h($securityEvent['email']) ?></td><td><span class="badge <?= $securityEvent['succeeded'] ? 'approved' : 'rejected' ?>"><?= $securityEvent['succeeded'] ? 'Successful' : 'Failed' ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
        <p class="muted"><?= (int) $adminAuditTotal ?> audit records</p>
        <?php if (!$adminAuditRows): ?><div class="empty-state">No audit records match the selected filters.</div><?php else: ?><div class="table-wrap"><table class="admin-table"><thead><tr><th>Timestamp</th><th>Actor / role</th><th>Action / module</th><th>Target</th><th>Result</th><th>Description</th></tr></thead><tbody><?php foreach ($adminAuditRows as $auditRow): ?><tr><td><?= h(date('M j, Y g:i A', strtotime((string) $auditRow['created_at']))) ?></td><td><?= h($auditRow['actor_name'] ?: 'System / unavailable') ?><small><?= h(ucfirst((string) $auditRow['actor_role'])) ?></small></td><td><?= h($auditRow['action']) ?><small><?= h($auditRow['entity_type'] ?: 'System') ?></small></td><td><?= $auditRow['entity_id'] === null ? '—' : '#' . (int) $auditRow['entity_id'] ?></td><td><span class="badge <?= $auditRow['result'] === 'failure' ? 'rejected' : 'approved' ?>"><?= h(ucfirst((string) $auditRow['result'])) ?></span></td><td><?= h($auditRow['description'] ?: $auditRow['action']) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php if ($adminAuditPageCount > 1): ?><nav class="admin-pagination" aria-label="Audit log pages"><?php for ($pageNumber = 1; $pageNumber <= $adminAuditPageCount; $pageNumber++): ?><a class="<?= $pageNumber === $adminAuditPage ? 'current' : '' ?>" href="index.php?<?= h(http_build_query(['page' => 'admin_audit', 'p' => $pageNumber] + $adminAuditFilters)) ?>"><?= $pageNumber ?></a><?php endfor; ?></nav><?php endif; ?>
        <?php endif; ?>

    <?php elseif ($page === 'admin_notifications'): ?>
        <section class="page-heading"><p class="eyebrow">COMPANY MANAGEMENT</p><h1>Company Notification Center</h1><p class="muted">Select company recipients, preview a template, then confirm delivery through the configured email provider.</p></section>
        <form class="card admin-notification-form" method="get">
            <input type="hidden" name="page" value="admin_notifications">
            <label>Company recipients <span class="field-help">Select multiple for bulk notifications</span><select name="company_ids[]" multiple size="7" required><?php foreach ($adminNotificationCompanies as $notificationCompany): ?><option value="<?= (int) $notificationCompany['id'] ?>" <?= in_array((int) $notificationCompany['id'], $adminNotificationSelected, true) ? 'selected' : '' ?>><?= h($notificationCompany['name']) ?> · <?= h(date('M j, Y', strtotime((string) $notificationCompany['created_at']))) ?></option><?php endforeach; ?></select></label>
            <label>Template<select name="template" required><?php foreach ($adminNotificationTemplates as $templateKey => $templateLabel): ?><option value="<?= h($templateKey) ?>" <?= $adminNotificationTemplate === $templateKey ? 'selected' : '' ?>><?= h($templateLabel) ?></option><?php endforeach; ?></select></label>
            <label>Custom message <span class="field-help">Required for custom announcements</span><textarea name="custom_message" rows="4" maxlength="5000"><?= h($adminNotificationTemplate === 'custom' ? $adminNotificationBody : '') ?></textarea></label>
            <button class="button" type="submit">Preview notification</button>
        </form>
        <?php if ($adminNotificationSelected && $adminNotificationTemplate): ?>
            <section class="card admin-notification-preview"><p class="eyebrow">PREVIEW · <?= count($adminNotificationSelected) ?> RECIPIENT<?= count($adminNotificationSelected) === 1 ? '' : 'S' ?></p><h2><?= h($adminNotificationSubject ?: 'Custom administrative announcement') ?></h2><p><?= nl2br(h($adminNotificationBody)) ?></p><p class="muted">Recipients: <?php foreach ($adminNotificationCompanies as $notificationCompany): if (in_array((int) $notificationCompany['id'], $adminNotificationSelected, true)): ?><?= h($notificationCompany['name']) ?> · <?php endif; endforeach; ?></p>
                <?php if ($adminNotificationBody !== ''): ?><form method="post" class="admin-send-confirm" onsubmit="return confirm('Send this reviewed notification to the selected company accounts?');"><?= csrf_field() ?><input type="hidden" name="action" value="admin_send_notifications"><?php foreach ($adminNotificationSelected as $companyId): ?><input type="hidden" name="company_ids[]" value="<?= (int) $companyId ?>"><?php endforeach; ?><input type="hidden" name="template" value="<?= h($adminNotificationTemplate) ?>"><input type="hidden" name="subject" value="<?= h($adminNotificationSubject ?: 'SMARTJOB administrative announcement') ?>"><input type="hidden" name="message" value="<?= h($adminNotificationBody) ?>"><label class="checkbox-label"><input type="checkbox" name="confirm_send" value="1" required> I reviewed the recipients and notification preview.</label><?php if (count($adminNotificationSelected) > 1): ?><label class="checkbox-label bulk-confirm"><input type="checkbox" name="confirm_bulk" value="1" required> I explicitly confirm this bulk send to <?= count($adminNotificationSelected) ?> company accounts.</label><?php endif; ?><button class="button" type="submit">Send notification</button></form><?php else: ?><div class="notice">Add a custom message, then preview again before sending.</div><?php endif; ?>
            </section>
        <?php endif; ?>
        <section class="card admin-panel" id="history"><header class="admin-panel-heading"><div><p class="eyebrow">DELIVERY ATTEMPTS</p><h2>Notification History</h2></div></header><?php if (!$adminNotificationHistory): ?><p class="muted">No administrative company notifications have been sent.</p><?php else: ?><div class="table-wrap"><table class="admin-table"><thead><tr><th>Attempt</th><th>Company / recipient</th><th>Template / subject</th><th>Delivery</th><th>Sent by</th><th>Details</th></tr></thead><tbody><?php foreach ($adminNotificationHistory as $historyRow): ?><tr><td><?= h(date('M j, Y g:i A', strtotime((string) $historyRow['created_at']))) ?></td><td><?= h($historyRow['company_name']) ?><small><?= h($historyRow['recipient_email']) ?></small></td><td><?= h($adminNotificationTemplates[$historyRow['template_key']] ?? $historyRow['template_key']) ?><small><?= h($historyRow['subject']) ?></small></td><td><span class="badge <?= h((string) $historyRow['delivery_status']) ?>"><?= h(ucfirst((string) $historyRow['delivery_status'])) ?></span></td><td><?= h($historyRow['sent_by_name'] ?: 'Unavailable') ?></td><td><?= h($historyRow['provider_name']) ?><?= $historyRow['http_status_code'] ? ' · HTTP ' . (int) $historyRow['http_status_code'] : '' ?><?= $historyRow['error_code'] ? ' · ' . h($historyRow['error_code']) : '' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

    <?php elseif ($page === 'companies'): ?>
        <?php
        $requirementsQuery = db()->query('SELECT company_type, document_type, applicability FROM tbl_company_verification_requirements');
        $requirementSettings = [];
        foreach ($requirementsQuery->fetchAll() as $requirementSetting) {
            $requirementSettings[$requirementSetting['company_type']][$requirementSetting['document_type']] = $requirementSetting['applicability'];
        }
        $requirementDescriptions = [
            'business_registration' => 'Official registration certificate for the business entity.',
            'business_permit' => 'Proof of authorization to operate the business.',
            'bir_registration' => 'BIR registration certificate for the organization.',
            'other_business_registration' => 'Additional business registration records requested for review.',
            'representative_id' => 'Valid identification for the authorized company representative.',
            'authorization' => 'Proof that the representative may act for the organization.',
            'representative_information' => 'Contact and role details for the authorized representative.',
            'memorandum' => 'Signed agreement establishing the OJT or internship partnership.',
            'training_plan' => 'Outline of the training activities and learning outcomes.',
            'internship_program' => 'Description of the internship program and assigned duties.',
            'supervisor_information' => 'Contact details and qualifications of the workplace supervisor.',
            'other' => 'Other institutional documents relevant to the placement.',
            'additional_certificate' => 'Additional certificates that support verification.',
            'accreditation' => 'Accreditation or quality-assurance evidence.',
            'supporting_letter' => 'Supporting letter requested by the institution.',
        ];
        $requirementIcons = [
            'business_registration' => 'bi-file-earmark-text',
            'business_permit' => 'bi-file-earmark-check',
            'bir_registration' => 'bi-receipt',
            'other_business_registration' => 'bi-files',
            'representative_id' => 'bi-person-vcard',
            'authorization' => 'bi-patch-check',
            'representative_information' => 'bi-person-lines-fill',
            'memorandum' => 'bi-file-earmark-ruled',
            'training_plan' => 'bi-journal-check',
            'internship_program' => 'bi-card-text',
            'supervisor_information' => 'bi-person-workspace',
            'other' => 'bi-file-earmark-plus',
            'additional_certificate' => 'bi-award',
            'accreditation' => 'bi-patch-check',
            'supporting_letter' => 'bi-envelope-paper',
        ];
        $companyTypeIcons = ['business' => 'bi-building', 'nonprofit' => 'bi-people', 'educational' => 'bi-mortarboard', 'government' => 'bi-bank', 'other' => 'bi-buildings'];
        ?>
        <form id="verification-policy-form" method="post" action="index.php?page=companies" class="verification-policy-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_company_requirements">
            <header class="verification-policy-header">
                <div><p class="eyebrow">SMARTVERIFY · POLICY CONFIGURATION</p><h1>Company Verification Requirements</h1><p class="muted">Configure the documents companies must submit based on their organization type.</p></div>
                <div class="verification-policy-actions"><span class="verification-unsaved" data-policy-dirty hidden><i class="bi bi-circle-fill" aria-hidden="true"></i> Unsaved changes</span><button class="button button-quiet" type="button" data-policy-reset disabled>Reset Unsaved Changes</button><button class="button" type="submit" data-policy-save disabled><i class="bi bi-check2-circle" aria-hidden="true"></i> Save Changes</button></div>
            </header>
            <div class="verification-policy-feedback" data-policy-feedback role="status" aria-live="polite" hidden></div>
            <section class="verification-policy-summary" aria-label="Requirement counts for selected organization type">
                <p class="verification-policy-summary-caption">Counts for <strong data-policy-summary-type>Business</strong> · selected organization type</p>
                <div class="verification-summary-grid">
                    <article class="verification-summary-card"><span>Total requirements</span><strong data-count-total>0</strong><i class="bi bi-files" aria-hidden="true"></i></article>
                    <article class="verification-summary-card mandatory"><span>Mandatory</span><strong data-count-required>0</strong><i class="bi bi-shield-check" aria-hidden="true"></i></article>
                    <article class="verification-summary-card optional"><span>Optional</span><strong data-count-optional>0</strong><i class="bi bi-circle-half" aria-hidden="true"></i></article>
                    <article class="verification-summary-card na"><span>Not applicable</span><strong data-count-na>0</strong><i class="bi bi-dash-circle" aria-hidden="true"></i></article>
                </div>
            </section>
            <section class="verification-policy-workspace">
                <div class="verification-policy-toolbar">
                    <div class="verification-type-tabs" role="tablist" aria-label="Organization type">
                        <?php foreach (\SMARTJOB\Modules\Company\Controllers\CompanyController::COMPANY_TYPES as $companyType => $companyTypeLabel): ?>
                            <button class="verification-type-tab" type="button" role="tab" id="policy-tab-<?= h($companyType) ?>" aria-controls="policy-panel-<?= h($companyType) ?>" aria-selected="<?= $companyType === 'business' ? 'true' : 'false' ?>" tabindex="<?= $companyType === 'business' ? '0' : '-1' ?>" data-policy-tab="<?= h($companyType) ?>"><i class="bi <?= h($companyTypeIcons[$companyType]) ?>" aria-hidden="true"></i><?= h($companyTypeLabel) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="verification-policy-filters">
                        <label class="verification-policy-search"><i class="bi bi-search" aria-hidden="true"></i><span class="sr-only">Search verification requirements</span><input type="search" placeholder="Search verification requirements..." data-policy-search></label>
                        <label class="verification-policy-filter"><span class="sr-only">Filter by requirement status</span><select data-policy-filter><option value="all">All statuses</option><option value="required">Mandatory</option><option value="optional">Optional</option><option value="not_applicable">Not applicable</option></select></label>
                    </div>
                </div>
                <?php foreach (\SMARTJOB\Modules\Company\Controllers\CompanyController::COMPANY_TYPES as $companyType => $companyTypeLabel): ?>
                    <section class="verification-policy-panel" role="tabpanel" id="policy-panel-<?= h($companyType) ?>" aria-labelledby="policy-tab-<?= h($companyType) ?>" data-policy-panel="<?= h($companyType) ?>" <?= $companyType === 'business' ? '' : 'hidden' ?>>
                        <?php foreach (\SMARTJOB\Modules\Company\Controllers\CompanyController::DOCUMENT_SECTIONS as $sectionName => $sectionRequirements): ?>
                            <section class="verification-policy-section" data-policy-section>
                                <header><span class="verification-category-icon"><i class="bi bi-folder2-open" aria-hidden="true"></i></span><div><h2><?= h($sectionName) ?></h2><p><?= count($sectionRequirements) ?> document requirements</p></div></header>
                                <div class="verification-policy-rows">
                                    <?php foreach ($sectionRequirements as $documentType => $documentLabel): ?>
                                        <?php
                                        $isGlobalRequired = in_array($documentType, \SMARTJOB\Modules\Company\Controllers\CompanyController::BUSINESS_REGISTRATION_TYPES, true);
                                        $applicability = $isGlobalRequired ? 'required' : ($requirementSettings[$companyType][$documentType] ?? 'optional');
                                        $searchableText = $documentLabel . ' ' . ($requirementDescriptions[$documentType] ?? '');
                                        ?>
                                        <article class="verification-policy-row" data-policy-row data-status="<?= h($applicability) ?>" data-search="<?= h(mb_strtolower($searchableText)) ?>">
                                            <span class="verification-document-icon"><i class="bi <?= h($requirementIcons[$documentType] ?? 'bi-file-earmark-text') ?>" aria-hidden="true"></i></span>
                                            <div class="verification-requirement-copy"><h3><?= h($documentLabel) ?></h3><p><?= h($requirementDescriptions[$documentType] ?? '') ?></p></div>
                                            <?php if ($isGlobalRequired): ?>
                                                <div class="verification-global-policy"><span class="verification-state-pill required"><i class="bi bi-lock-fill" aria-hidden="true"></i> Mandatory</span><small>Required for all company types</small><input type="hidden" name="requirements[<?= h($companyType) ?>][<?= h($documentType) ?>]" value="required"></div>
                                            <?php else: ?>
                                                <fieldset class="verification-segmented-control" aria-label="<?= h($documentLabel) ?> requirement status">
                                                    <legend class="sr-only"><?= h($documentLabel) ?> requirement status</legend>
                                                    <?php foreach (\SMARTJOB\Modules\Company\Controllers\CompanyController::REQUIREMENT_APPLICABILITY as $status => $statusLabel): ?>
                                                        <?php $inputId = 'requirement-' . $companyType . '-' . $documentType . '-' . $status; ?>
                                                        <label class="verification-state-option <?= h($status) ?>" for="<?= h($inputId) ?>"><input id="<?= h($inputId) ?>" type="radio" name="requirements[<?= h($companyType) ?>][<?= h($documentType) ?>]" value="<?= h($status) ?>" <?= $applicability === $status ? 'checked' : '' ?>><span><?= h($statusLabel) ?></span></label>
                                                    <?php endforeach; ?>
                                                </fieldset>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                        <p class="verification-policy-no-results" data-policy-no-results hidden>No requirements match this search and filter.</p>
                    </section>
                <?php endforeach; ?>
            </section>
        </form>
        <?php
        $selectedCompanyId = filter_var($_GET['company_id'] ?? null, FILTER_VALIDATE_INT);
        $companyFilterSql = $selectedCompanyId !== false && $selectedCompanyId !== null && $selectedCompanyId > 0 ? ' WHERE c.id=?' : '';
        $companyListQuery = db()->prepare("SELECT c.id, c.name, c.company_type, c.industry, c.description, c.website, c.address, c.verification_status, c.created_at, u.full_name AS owner_name, u.email AS owner_email, v.status AS review_status, v.reviewer_notes, (EXISTS (SELECT 1 FROM tbl_company_documents d WHERE d.company_id=c.id AND NOT EXISTS (SELECT 1 FROM tbl_company_documents newer WHERE newer.previous_document_id=d.id)) AND NOT EXISTS (SELECT 1 FROM tbl_company_documents d WHERE d.company_id=c.id AND NOT EXISTS (SELECT 1 FROM tbl_company_documents newer WHERE newer.previous_document_id=d.id) AND d.review_status<>'approved') AND NOT EXISTS (SELECT 1 FROM tbl_company_verification_requirements r WHERE r.company_type=c.company_type AND r.applicability='required' AND NOT EXISTS (SELECT 1 FROM tbl_company_documents rd WHERE rd.company_id=c.id AND rd.document_type=r.document_type AND NOT EXISTS (SELECT 1 FROM tbl_company_documents newer_required WHERE newer_required.previous_document_id=rd.id)))) AS final_review_ready FROM tbl_companies c JOIN tbl_company_members m ON m.company_id=c.id AND m.member_role='owner' JOIN tbl_users u ON u.id=m.user_id LEFT JOIN tbl_company_verifications v ON v.company_id=c.id" . $companyFilterSql . " ORDER BY FIELD(c.verification_status, 'pending', 'rejected', 'approved'), c.created_at DESC");
        $companyListQuery->execute($companyFilterSql !== '' ? [(int) $selectedCompanyId] : []);
        $companies = $companyListQuery->fetchAll();
        $verificationHistory = [];
        if ($companyFilterSql !== '') {
            $historyQuery = db()->prepare('SELECT l.decision, l.notes, l.created_at, u.full_name AS reviewer_name FROM tbl_verification_logs l LEFT JOIN tbl_users u ON u.id=l.reviewer_id WHERE l.company_id=? ORDER BY l.created_at DESC, l.id DESC');
            $historyQuery->execute([(int) $selectedCompanyId]);
            $verificationHistory = $historyQuery->fetchAll();
        }
        ?>
        <section class="page-heading"><p class="eyebrow">SMARTVERIFY</p><h1>Company verification</h1><p class="muted">Review company profiles and each submitted verification document. Return correctable documents with a clear reason.</p></section>
        <?php if ($selectedCompanyId !== false && $selectedCompanyId !== null && $selectedCompanyId > 0): ?><p><a class="text-link" href="index.php?page=companies">← All company verification requests</a></p><?php endif; ?>
        <?php if (!$companies): ?>
            <div class="empty-state">No company accounts have been submitted.</div>
        <?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Company</th><th>Representative</th><th>Status</th><th>Submitted</th><th>Review</th></tr></thead><tbody>
                <?php foreach ($companies as $company): ?>
                    <tr id="company-<?= (int) $company['id'] ?>">
                        <td>
                            <strong><?= h($company['name']) ?></strong>
                            <small><?= h(\SMARTJOB\Modules\Company\Controllers\CompanyController::COMPANY_TYPES[$company['company_type']] ?? 'Other organization') ?> · <?= h($company['industry'] ?: 'Industry not listed') ?><?= $company['address'] ? ' · ' . h($company['address']) : '' ?></small>
                            <details class="verification-details"><summary>Profile details</summary>
                                <p><?= nl2br(h($company['description'] ?: 'No company description provided.')) ?></p>
                                <?php if (!empty($company['website']) && filter_var($company['website'], FILTER_VALIDATE_URL)): ?><a class="text-link" href="<?= h($company['website']) ?>" target="_blank" rel="noopener noreferrer">Visit company website ↗</a><?php endif; ?>
                            </details>
                            <?php if (!empty($company['reviewer_notes'])): ?><small class="verification-review-note"><strong>Latest review:</strong> <?= h($company['reviewer_notes']) ?></small><?php endif; ?>
                        </td>
                        <td><?= h($company['owner_name']) ?><small><?= h($company['owner_email']) ?></small></td>
                        <td><span class="badge <?= h((string) ($company['review_status'] ?: $company['verification_status'])) ?>"><?= h(ucwords(str_replace('_', ' ', (string) ($company['review_status'] ?: $company['verification_status']))) ) ?></span></td>
                        <td><?= h(date('M j, Y', strtotime((string) $company['created_at']))) ?></td>
                        <td>
                            <?php if ($company['verification_status'] !== 'approved'): ?>
                                <form method="post" class="company-review-form"><?= csrf_field() ?><input type="hidden" name="action" value="review_company"><input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                                    <label class="sr-only" for="review-note-<?= (int) $company['id'] ?>">Review note (required for revision requests)</label>
                                    <textarea id="review-note-<?= (int) $company['id'] ?>" name="remarks" maxlength="1000" rows="2" placeholder="Review note; required for a revision request"></textarea>
                                    <div class="review-actions"><?php if ($company['final_review_ready']): ?><button class="button button-small" name="decision" value="approved">Complete verification</button><?php else: ?><span class="muted">Approve all required and submitted current documents before final verification.</span><?php endif; ?><button class="button button-small button-quiet" name="decision" value="revision_requested">Request revisions</button><button class="button button-small button-danger" name="decision" value="rejected">Reject</button></div>
                                </form>
                            <?php else: ?><span class="muted">Approved</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php if ($companyFilterSql !== ''): ?><section class="card verification-history-section"><h2>Verification history</h2><?php if (!$verificationHistory): ?><p class="muted">No company verification decisions have been recorded yet.</p><?php else: ?><ol class="admin-verification-history"><?php foreach ($verificationHistory as $historyEntry): ?><li><strong><?= h(ucwords(str_replace('_', ' ', $historyEntry['decision']))) ?></strong><span><?= h($historyEntry['reviewer_name'] ?: 'Administrator') ?> · <?= h(date('M j, Y g:i A', strtotime((string) $historyEntry['created_at']))) ?></span><?php if (!empty($historyEntry['notes'])): ?><p><?= nl2br(h($historyEntry['notes'])) ?></p><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?></section><?php endif; ?>
        <?php $companyDocuments = db()->query('SELECT d.id, d.company_id, d.document_type, d.original_name, d.review_status, d.reviewer_notes, d.version_number, d.created_at, b.batch_key, c.name AS company_name FROM tbl_company_documents d JOIN tbl_companies c ON c.id=d.company_id LEFT JOIN tbl_company_document_batches b ON b.id=d.batch_id ORDER BY FIELD(d.review_status, \'pending\', \'under_review\', \'revision_requested\', \'approved\'), d.created_at DESC')->fetchAll(); ?>
        <section class="page-heading"><p class="eyebrow">DOCUMENT-BY-DOCUMENT REVIEW</p><h2>Verification documents</h2><p class="muted">Each submission is private to the company and administrators. Approving a document does not approve the company account.</p></section>
        <?php if (!$companyDocuments): ?><div class="empty-state">No company documents have been submitted yet.</div><?php else: ?>
            <?php foreach (\SMARTJOB\Modules\Company\Controllers\CompanyController::DOCUMENT_SECTIONS as $sectionName => $sectionRequirements): ?>
                <?php $sectionDocuments = array_values(array_filter($companyDocuments, static fn (array $document): bool => isset($sectionRequirements[$document['document_type']]))); ?>
                <?php if ($sectionDocuments): ?><section class="card verification-history-section"><h3><?= h($sectionName) ?></h3><div class="table-wrap"><table><thead><tr><th>Company / requirement</th><th>Document</th><th>Status</th><th>Batch / version</th><th>Uploaded</th><th>Review history</th><th>Decision</th></tr></thead><tbody>
                    <?php foreach ($sectionDocuments as $companyDocument): ?>
                        <tr>
                            <td><strong><?= h($companyDocument['company_name']) ?></strong><small><?= h($sectionRequirements[$companyDocument['document_type']]) ?></small></td>
                            <td><a class="button button-small button-quiet" href="index.php?page=company_document&amp;id=<?= (int) $companyDocument['id'] ?>">Preview / Download</a><small><?= h($companyDocument['original_name']) ?></small><?php if (!empty($companyDocument['reviewer_notes'])): ?><small class="verification-review-note"><?= h($companyDocument['reviewer_notes']) ?></small><?php endif; ?></td>
                            <td><span class="badge <?= h($companyDocument['review_status']) ?>"><?= h(ucwords(str_replace('_', ' ', $companyDocument['review_status']))) ?></span></td>
                            <td><?= $companyDocument['batch_key'] ? h($companyDocument['batch_key']) : 'Legacy submission' ?><small>Version <?= (int) $companyDocument['version_number'] ?></small></td>
                            <td><?= h(date('M j, Y', strtotime((string) $companyDocument['created_at']))) ?></td>
                            <td><details><summary>View history</summary><?php $historyQuery = db()->prepare('SELECT h.action, h.notes, h.created_at, u.full_name FROM tbl_company_document_review_history h LEFT JOIN tbl_users u ON u.id=h.reviewer_id WHERE h.document_id=? ORDER BY h.id DESC'); $historyQuery->execute([(int) $companyDocument['id']]); foreach ($historyQuery->fetchAll() as $history): ?><small><?= h(ucwords(str_replace('_', ' ', $history['action']))) ?> · <?= h($history['full_name'] ?: 'System') ?> · <?= h(date('M j, Y g:i A', strtotime((string) $history['created_at']))) ?><?= $history['notes'] ? ': ' . h($history['notes']) : '' ?></small><?php endforeach; ?></details></td>
                            <td><?php if (in_array($companyDocument['review_status'], ['pending', 'under_review'], true)): ?><form method="post" class="company-review-form"><?= csrf_field() ?><input type="hidden" name="action" value="review_company_document"><input type="hidden" name="document_id" value="<?= (int) $companyDocument['id'] ?>"><textarea name="notes" maxlength="1000" rows="2" placeholder="Reason required when returning for revision"></textarea><div class="review-actions"><button class="button button-small button-quiet" name="decision" value="under_review">Under review</button><button class="button button-small" name="decision" value="approved">Approve document</button><button class="button button-small button-danger" name="decision" value="revision_requested">Return for revision</button></div></form><?php else: ?><span class="muted">Review complete</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table></div></section><?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php elseif ($page === 'company_verification'): ?>
        <?php
        $companyId = current_company_id((int) $user['id']);
        $companyQuery = db()->prepare('SELECT name, company_type, verification_status FROM tbl_companies WHERE id=?');
        $companyQuery->execute([$companyId]);
        $verificationCompany = $companyQuery->fetch();
        $documentQuery = db()->prepare('SELECT d.id, d.document_type, d.original_name, d.review_status, d.reviewer_notes, d.previous_document_id, d.version_number, d.created_at, b.batch_key, NOT EXISTS (SELECT 1 FROM tbl_company_documents newer WHERE newer.previous_document_id=d.id) AS is_current FROM tbl_company_documents d LEFT JOIN tbl_company_document_batches b ON b.id=d.batch_id WHERE d.company_id=? ORDER BY d.created_at DESC, d.id DESC');
        $documentQuery->execute([$companyId]);
        $companyDocuments = $documentQuery->fetchAll();
        $currentDocuments = array_values(array_filter($companyDocuments, static fn (array $document): bool => (bool) $document['is_current']));
        $approvedDocuments = count(array_filter($currentDocuments, static fn (array $document): bool => $document['review_status'] === 'approved'));
        $requirementQuery = db()->prepare('SELECT document_type, applicability FROM tbl_company_verification_requirements WHERE company_type=?');
        $requirementQuery->execute([$verificationCompany['company_type'] ?? 'other']);
        $requirementSettings = [];
        foreach ($requirementQuery->fetchAll() as $requirement) {
            $requirementSettings[$requirement['document_type']] = $requirement['applicability'];
        }
        $fileLimit = \CompanyDocumentStorage::maxFileBytes();
        $batchLimit = \CompanyDocumentStorage::maxBatchBytes();
        ?>
        <section class="page-heading"><p class="eyebrow">SMARTVERIFY</p><h1>Company verification</h1><p class="muted"><?= h($verificationCompany['name'] ?? 'Your company') ?> · <?= h(\SMARTJOB\Modules\Company\Controllers\CompanyController::COMPANY_TYPES[$verificationCompany['company_type'] ?? 'other']) ?> · <?= $approvedDocuments ?> of <?= count($currentDocuments) ?> current document attachments approved. Final verification remains a separate administrator decision.</p></section>
        <div class="status-banner"><span class="status-dot <?= ($verificationCompany['verification_status'] ?? '') === 'approved' ? 'green' : '' ?>"></span><div><b>Account status: <?= h(ucwords(str_replace('_', ' ', (string) ($verificationCompany['verification_status'] ?? 'pending')))) ?></b><span>Company features that require verification remain unavailable until an administrator completes the final review.</span></div></div>
        <form id="company-documents-form" class="verification-upload-form" method="post" enctype="multipart/form-data" data-max-file-bytes="<?= $fileLimit ?>" data-max-batch-bytes="<?= $batchLimit ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="upload_company_documents">
            <section class="card form-card verification-upload-intro"><h2>Prepare verification documents</h2><p class="muted">Select files across any sections, then submit them together. Allowed: PDF, DOCX, JPG, and PNG. Maximum <?= h((string) round($fileLimit / 1_048_576, 1)) ?> MB per file and <?= h((string) round($batchLimit / 1_048_576, 1)) ?> MB per submission.</p><strong><?= $approvedDocuments ?> / <?= count($currentDocuments) ?> current attachments approved</strong></section>
            <?php foreach (\SMARTJOB\Modules\Company\Controllers\CompanyController::DOCUMENT_SECTIONS as $sectionName => $sectionRequirements): ?>
                <section class="card verification-upload-section" aria-label="<?= h($sectionName) ?>">
                    <div class="verification-section-heading"><div><p class="eyebrow">DOCUMENT CATEGORY</p><h2><?= h($sectionName) ?></h2></div></div>
                    <?php foreach ($sectionRequirements as $documentType => $documentLabel): ?>
                        <?php $typeDocuments = array_values(array_filter($currentDocuments, static fn (array $document): bool => $document['document_type'] === $documentType)); $applicability = $requirementSettings[$documentType] ?? 'optional'; $typeStatuses = array_unique(array_column($typeDocuments, 'review_status')); $typeStatus = !$typeDocuments ? 'not_submitted' : (count($typeStatuses) > 1 ? 'under_review' : $typeStatuses[0]); ?>
                        <article class="verification-requirement" data-requirement="<?= h($documentType) ?>">
                            <div class="verification-requirement-heading"><div><h3><?= h($documentLabel) ?></h3><span class="badge <?= h($applicability) ?>"><?= h(\SMARTJOB\Modules\Company\Controllers\CompanyController::REQUIREMENT_APPLICABILITY[$applicability] ?? 'Optional') ?></span></div>
                                <?php if ($typeDocuments): ?><span class="badge <?= h($typeStatus) ?>"><?= h(count($typeStatuses) > 1 ? 'Mixed review status' : ucwords(str_replace('_', ' ', $typeStatus))) ?><?= count($typeDocuments) > 1 ? ' · ' . count($typeDocuments) . ' files' : '' ?></span><?php else: ?><span class="badge not_submitted">Not submitted</span><?php endif; ?>
                            </div>
                            <?php foreach ($typeDocuments as $typeDocument): ?>
                                <?php if ($typeDocument['review_status'] === 'revision_requested'): ?>
                                    <div class="verification-returned" data-replacement-drop>
                                        <strong>Revision requested: <?= h($typeDocument['original_name']) ?></strong>
                                        <?php if ($typeDocument['reviewer_notes']): ?><p><?= nl2br(h($typeDocument['reviewer_notes'])) ?></p><?php endif; ?>
                                        <label>Choose a replacement<input type="file" name="replacements[<?= (int) $typeDocument['id'] ?>]" accept=".pdf,.docx,.jpg,.jpeg,.png,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png" data-replacement-id="<?= (int) $typeDocument['id'] ?>" data-document-label="<?= h($documentLabel) ?>" data-file-picker></label>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <label class="verification-dropzone" data-upload-drop>
                                <span>Drop files here or <strong>browse</strong></span>
                                <small>Multiple attachments are supported for this requirement.</small>
                                <input type="file" name="documents[<?= h($documentType) ?>][]" multiple accept=".pdf,.docx,.jpg,.jpeg,.png,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png" data-document-type="<?= h($documentType) ?>" data-document-label="<?= h($documentLabel) ?>" data-file-picker>
                            </label>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
            <section class="card verification-upload-preview" aria-live="polite">
                <div class="verification-section-heading"><div><p class="eyebrow">UPLOAD PREVIEW</p><h2 data-upload-count>0 documents ready to upload</h2></div></div>
                <p class="muted" data-upload-empty>Select or drop files above to review this submission.</p>
                <ul class="verification-preview-list" data-upload-preview></ul>
                <div class="verification-progress" data-upload-progress hidden><progress max="100" value="0" data-progress-bar></progress><span data-progress-label>Preparing upload…</span></div>
                <p class="notice error" data-upload-error hidden role="alert"></p>
                <p class="notice success" data-upload-success hidden role="status"></p>
                <button class="button" type="submit" data-upload-submit>Submit all selected documents</button>
            </section>
        </form>
        <section class="page-heading"><h2>Submitted documents and version history</h2><p class="muted">Documents remain individually reviewable, even when submitted in one batch.</p></section>
        <?php foreach (\SMARTJOB\Modules\Company\Controllers\CompanyController::DOCUMENT_SECTIONS as $sectionName => $sectionRequirements): ?>
            <section class="card verification-history-section"><h3><?= h($sectionName) ?></h3>
                <?php $sectionDocuments = array_values(array_filter($companyDocuments, static fn (array $document): bool => isset($sectionRequirements[$document['document_type']]))); ?>
                <?php if (!$sectionDocuments): ?><p class="muted">No documents in this section yet.</p><?php else: ?>
                    <div class="table-wrap"><table><thead><tr><th>Requirement / document</th><th>Status and feedback</th><th>Batch / version</th><th>Uploaded</th><th>Review history</th></tr></thead><tbody>
                    <?php foreach ($sectionDocuments as $companyDocument): ?>
                        <tr><td><strong><?= h($sectionRequirements[$companyDocument['document_type']]) ?></strong><small><a class="text-link" href="index.php?page=company_document&amp;id=<?= (int) $companyDocument['id'] ?>"><?= h($companyDocument['original_name']) ?></a><?= empty($companyDocument['is_current']) ? ' · Previous version' : '' ?></small></td>
                            <td><span class="badge <?= h($companyDocument['review_status']) ?>"><?= h(ucwords(str_replace('_', ' ', $companyDocument['review_status']))) ?></span><?php if (!empty($companyDocument['reviewer_notes'])): ?><small class="verification-review-note"><?= h($companyDocument['reviewer_notes']) ?></small><?php endif; ?></td>
                            <td><?= $companyDocument['batch_key'] ? h($companyDocument['batch_key']) : 'Legacy submission' ?><small>Version <?= (int) $companyDocument['version_number'] ?></small></td>
                            <td><?= h(date('M j, Y g:i A', strtotime((string) $companyDocument['created_at']))) ?></td>
                            <td><details><summary>View history</summary><?php $historyQuery = db()->prepare('SELECT h.action, h.notes, h.created_at, u.full_name FROM tbl_company_document_review_history h LEFT JOIN tbl_users u ON u.id=h.reviewer_id WHERE h.document_id=? ORDER BY h.id DESC'); $historyQuery->execute([(int) $companyDocument['id']]); foreach ($historyQuery->fetchAll() as $history): ?><small><?= h(ucwords(str_replace('_', ' ', $history['action']))) ?> · <?= h($history['full_name'] ?: 'System') ?> · <?= h(date('M j, Y g:i A', strtotime((string) $history['created_at']))) ?><?= $history['notes'] ? ': ' . h($history['notes']) : '' ?></small><?php endforeach; ?></details></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

    <?php elseif ($page === 'company_profile'): ?>
        <?php
        $companyId = current_company_id((int) $user['id']);
        $companyStmt = db()->prepare('SELECT c.name, c.company_type, c.industry, c.description, c.mission_statement, c.vision_statement, c.address, c.website, c.logo_key, c.verification_status, v.status AS review_status, v.reviewer_notes FROM tbl_companies c LEFT JOIN tbl_company_verifications v ON v.company_id=c.id WHERE c.id=?');
        $companyStmt->execute([$companyId]);
        $company = $companyStmt->fetch() ?: [];
        ?>
        <section class="page-heading"><p class="eyebrow">YOUR ORGANIZATION</p><h1>Company profile</h1><p class="muted">Manage the company information and logo shown with your internship opportunities.</p></section>
        <?php if (($company['review_status'] ?? '') === 'revision_requested'): ?><div class="notice info"><strong>Updates requested.</strong> <?= h($company['reviewer_notes'] ?? 'Please update your company profile, then save it to resubmit for review.') ?></div><?php endif; ?>
        <?php if (!empty($company['logo_key'])): ?><div class="company-profile-logo"><img class="company-logo" src="index.php?page=company_logo&amp;id=<?= $companyId ?>" alt="<?= h($company['name'] ?? 'Company') ?> logo"></div><?php else: ?><div class="company-profile-logo"><span class="company-mark company-mark-large" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($company['name'] ?? 'Company', 0, 1))) ?></span></div><?php endif; ?>
        <form id="company-profile" class="card form-card compact-form company-editor" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save_company_profile"><h2>Company information</h2><p class="muted">These details appear in the public Explore directory. Organization type determines which verification documents are required.</p><div class="form-grid"><label>Company name<input name="company_name" maxlength="180" value="<?= h($company['name'] ?? '') ?>" required></label><label>Organization type<select name="company_type" required><?php foreach (\SMARTJOB\Modules\Company\Controllers\CompanyController::COMPANY_TYPES as $companyType => $companyTypeLabel): ?><option value="<?= h($companyType) ?>" <?= ($company['company_type'] ?? 'other') === $companyType ? 'selected' : '' ?>><?= h($companyTypeLabel) ?></option><?php endforeach; ?></select></label><label>Industry<input name="industry" maxlength="120" value="<?= h($company['industry'] ?? '') ?>" placeholder="e.g. Information Technology"></label><label>Location<input name="address" maxlength="255" value="<?= h($company['address'] ?? '') ?>" placeholder="City, province"></label><label>Website<input name="website" type="url" maxlength="255" value="<?= h($company['website'] ?? '') ?>" placeholder="https://example.com"></label></div><label>Company description<textarea name="description" data-ai-writing="employer_company_description" rows="3" maxlength="10000"><?= h($company['description'] ?? '') ?></textarea></label><label>Mission statement<textarea name="mission_statement" data-ai-writing="employer_mission_statement" rows="3" maxlength="5000"><?= h($company['mission_statement'] ?? '') ?></textarea></label><label>Vision statement<textarea name="vision_statement" data-ai-writing="employer_vision_statement" rows="3" maxlength="5000"><?= h($company['vision_statement'] ?? '') ?></textarea></label><label>Company logo <span class="field-help">JPG or PNG · max 5 MB</span><input type="file" name="company_logo" accept="image/jpeg,image/png,.jpg,.jpeg,.png"></label><button class="button button-small" type="submit">Save company profile</button></form>

    <?php elseif ($page === 'internships'): ?>
        <?php
        if ($user && $user['role'] === 'employer') {
            $companyId = current_company_id((int) $user['id']);
            $companyStmt = db()->prepare('SELECT c.name, c.verification_status, v.status AS review_status, v.reviewer_notes FROM tbl_companies c LEFT JOIN tbl_company_verifications v ON v.company_id=c.id WHERE c.id=?'); $companyStmt->execute([$companyId]); $company = $companyStmt->fetch() ?: []; $isApproved = ($company['verification_status'] ?? '') === 'approved';
            if ($isApproved) {
                $stmt = db()->prepare('SELECT i.*, c.id AS company_id, c.name AS company_name, c.logo_key, (SELECT COUNT(*) FROM tbl_applications a WHERE a.internship_id=i.id) AS applicant_count FROM tbl_internship_opportunities i JOIN tbl_companies c ON c.id=i.company_id WHERE i.company_id=? ORDER BY i.published_at DESC, i.id DESC');
                $stmt->execute([$companyId]); $opportunities = $stmt->fetchAll();
            } else {
                $opportunities = [];
            }
        } else {
            $opportunities = db()->query("SELECT i.*, c.id AS company_id, c.name AS company_name, c.logo_key FROM tbl_internship_opportunities i JOIN tbl_companies c ON c.id=i.company_id WHERE i.status='published' AND c.verification_status='approved' ORDER BY i.published_at DESC, i.id DESC")->fetchAll();
        }
        $activeInternshipCount = count(array_filter($opportunities, static fn (array $opportunity): bool => \HiringCountdown::status((string) $opportunity['application_deadline'], (string) $opportunity['status'])['can_apply']));
        $draftInternshipCount = count(array_filter($opportunities, static fn (array $opportunity): bool => $opportunity['status'] === 'draft'));
        $closedInternshipCount = count(array_filter($opportunities, static fn (array $opportunity): bool => \HiringCountdown::status((string) $opportunity['application_deadline'], (string) $opportunity['status'])['state'] === 'closed'));
        $totalApplicantCount = array_sum(array_map(static fn (array $opportunity): int => (int) ($opportunity['applicant_count'] ?? 0), $opportunities));
        ?>
        <section id="opportunities" class="page-heading internship-heading"><div><p class="eyebrow">OPPORTUNITIES</p><h1><?= $user && $user['role'] === 'employer' ? 'Your internships' : 'Find your fit' ?></h1><p class="muted"><?= $user && $user['role'] === 'employer' ? 'Manage your company’s published internship opportunities.' : 'Browse approved companies and see each opportunity’s application status.' ?></p><?php if ($user && $user['role'] === 'employer'): ?><a class="text-link" href="index.php?page=company_verification">Upload or review company verification documents →</a><?php endif; ?></div><?php if ($user && $user['role'] === 'employer' && $isApproved): ?><a class="button" href="#create-internship">Create Internship</a><?php endif; ?></section>
        <?php if ($user && $user['role'] === 'employer' && ($company['review_status'] ?? '') === 'revision_requested'): ?><div class="notice info"><strong>Updates requested.</strong> <?= h($company['reviewer_notes'] ?? 'Please update your company profile, then save it to resubmit for review.') ?></div><?php endif; ?>
        <?php if ($user && $user['role'] === 'employer' && $isApproved): ?><div class="stat-grid internship-stat-grid"><article class="stat-card"><span>Active internships</span><strong><?= $activeInternshipCount ?></strong></article><article class="stat-card"><span>Draft internships</span><strong><?= $draftInternshipCount ?></strong></article><article class="stat-card"><span>Closed internships</span><strong><?= $closedInternshipCount ?></strong></article><article class="stat-card"><span>Total applicants</span><strong><?= $totalApplicantCount ?></strong></article></div><?php endif; ?>
        <?php if ($user && $user['role'] === 'employer' && $isApproved): ?>
            <form id="create-internship" class="card form-card compact-form" method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="create_internship">
                <h2>Create an internship</h2>
                <div class="form-grid">
                    <label>Position title<input name="title" maxlength="180" required></label>
                    <label>Internship category<input name="category" maxlength="100" placeholder="e.g. Software development"></label>
                    <label>Location<input name="location" maxlength="180" placeholder="e.g. Quezon City"></label>
                    <label>Work mode<select name="work_mode"><option value="onsite">On-site</option><option value="hybrid">Hybrid</option><option value="remote">Remote</option></select></label>
                    <label>Available slots<input name="slots" type="number" min="1" max="500" value="1" required></label>
                    <label>Hiring duration<select name="hiring_duration_preset"><option value="7">7 days</option><option value="15">15 days</option><option value="30" selected>30 days</option><option value="45">45 days</option><option value="60">60 days</option><option value="90">90 days</option><option value="custom">Custom (1–90 days)</option></select></label>
                    <label>Custom duration in days<input name="custom_hiring_duration_days" type="number" min="1" max="90" value="30"></label>
                </div>
                <label>Opportunity details<textarea name="description" data-ai-writing="employer_internship_description" rows="4" maxlength="12000" required></textarea></label>
                <label>Requirements, responsibilities, and application details<textarea name="requirements" data-ai-writing="employer_internship_qualifications" data-ai-writing-options="employer_internship_responsibilities,employer_internship_qualifications,employer_preferred_skills,employer_learning_objectives,employer_training_plan" rows="3" maxlength="5000" placeholder="Required skills, documents, or other application instructions"></textarea></label>
                <button class="button" type="submit">Publish internship</button>
            </form>
        <?php elseif ($user && $user['role'] === 'employer'): ?><div class="notice">Your company must be approved before you can publish an opportunity.</div><?php endif; ?>
        <?php if ($user && $user['role'] === 'employer' && $isApproved): ?>
            <section class="card form-card compact-form">
                <h2>Manage opportunities</h2>
                <?php if (!$opportunities): ?><p class="muted">You have not published any opportunities yet.</p><?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Position</th><th>Applicants</th><th>Posted</th><th>Duration</th><th>Deadline</th><th>Remaining</th><th>Slots</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($opportunities as $opportunity): ?>
                                <tr>
                                    <td><span class="company-mark company-mark-small internship-listing-logo" aria-hidden="true"><?php if (!empty($opportunity['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $opportunity['company_id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($opportunity['company_name'], 0, 1))) ?><?php endif; ?></span><strong><?= h($opportunity['title']) ?></strong><small><?= h($opportunity['category'] ?: 'General internship') ?> · <?= h($opportunity['location'] ?: 'Location not specified') ?></small></td>
                                    <?php $hiringStatus = \HiringCountdown::status((string) $opportunity['application_deadline'], (string) $opportunity['status']); ?>
                                    <td><?= (int) $opportunity['applicant_count'] ?></td>
                                    <td><?= h(\HiringCountdown::postedDate((string) $opportunity['published_at'])) ?></td>
                                    <td><?= (int) $opportunity['hiring_duration_days'] ?> days</td>
                                    <td><?= h(date('M j, Y', strtotime((string) $opportunity['application_deadline']))) ?></td>
                                    <td><?= $hiringStatus['state'] === 'closed' ? '—' : (int) $hiringStatus['days_remaining'] . ' days' ?></td>
                                    <td><?= (int) $opportunity['slots'] ?></td>
                                    <td><span class="badge <?= h($hiringStatus['badge_class']) ?>"><?= h($hiringStatus['label']) ?></span></td>
                                    <td>
                                        <details class="opportunity-edit"><summary>Edit</summary>
                                            <?php $durationOptions = [7, 15, 30, 45, 60, 90]; $knownDuration = in_array((int) $opportunity['hiring_duration_days'], $durationOptions, true); ?>
                                            <form method="post" class="opportunity-edit-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_internship"><input type="hidden" name="internship_id" value="<?= (int) $opportunity['id'] ?>">
                                                <label>Position title<input name="title" maxlength="180" value="<?= h($opportunity['title']) ?>" required></label>
                                                <label>Category<input name="category" maxlength="100" value="<?= h($opportunity['category'] ?? '') ?>"></label>
                                                <label>Location<input name="location" maxlength="180" value="<?= h($opportunity['location'] ?? '') ?>"></label>
                                                <label>Work mode<select name="work_mode"><?php foreach (['onsite' => 'On-site', 'hybrid' => 'Hybrid', 'remote' => 'Remote'] as $mode => $label): ?><option value="<?= h($mode) ?>" <?= $opportunity['work_mode'] === $mode ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
                                                <label>Available slots<input name="slots" type="number" min="1" max="500" value="<?= (int) $opportunity['slots'] ?>" required></label>
                                                <label>Hiring duration<select name="hiring_duration_preset"><?php foreach ($durationOptions as $durationOption): ?><option value="<?= $durationOption ?>" <?= $knownDuration && (int) $opportunity['hiring_duration_days'] === $durationOption ? 'selected' : '' ?>><?= $durationOption ?> days</option><?php endforeach; ?><option value="custom" <?= !$knownDuration ? 'selected' : '' ?>>Custom (1–90 days)</option></select></label>
                                                <label>Custom duration in days<input name="custom_hiring_duration_days" type="number" min="1" max="<?= max(90, (int) $opportunity['hiring_duration_days']) ?>" value="<?= (int) $opportunity['hiring_duration_days'] ?>"></label>
                                                <label>Hiring status<select name="posting_status"><?php foreach (['draft' => 'Draft', 'published' => 'Open or reopen applications', 'paused' => 'Paused', 'closed' => 'Closed'] as $statusValue => $statusLabel): ?><option value="<?= h($statusValue) ?>" <?= $opportunity['status'] === $statusValue ? 'selected' : '' ?>><?= h($statusLabel) ?></option><?php endforeach; ?></select></label>
                                                <label>Opportunity details<textarea name="description" data-ai-writing="employer_internship_description" rows="4" maxlength="12000" required><?= h($opportunity['description']) ?></textarea></label>
                                                <label>Requirements, responsibilities, and application details<textarea name="requirements" data-ai-writing="employer_internship_qualifications" data-ai-writing-options="employer_internship_responsibilities,employer_internship_qualifications,employer_preferred_skills,employer_learning_objectives,employer_training_plan" rows="3" maxlength="5000"><?= h($opportunity['requirements'] ?? '') ?></textarea></label>
                                                <button class="button button-small" type="submit">Save changes</button>
                                            </form>
                                        </details>
                                        <?php if ($opportunity['status'] !== 'closed'): ?><form method="post" class="inline-review" onsubmit="return confirm('Close this opportunity? It will stop accepting applications.');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_internship"><input type="hidden" name="internship_id" value="<?= (int) $opportunity['id'] ?>"><button class="button button-small button-danger" type="submit">Close</button></form><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if (!$user || $user['role'] !== 'employer'): ?>
            <?php if (!$opportunities): ?><div class="empty-state">No opportunities to show yet.</div><?php else: ?>
                <div class="opportunity-grid">
                    <?php foreach ($opportunities as $item): ?>
                        <?php $itemHiringStatus = \HiringCountdown::status((string) $item['application_deadline'], (string) $item['status']); ?>
                        <article class="card opportunity-card">
                            <div class="opportunity-top"><span class="badge <?= h($itemHiringStatus['badge_class']) ?>"><?= h($itemHiringStatus['label']) ?></span><span class="muted"><?= (int) $item['slots'] ?> slot<?= (int) $item['slots'] === 1 ? '' : 's' ?></span></div>
                            <div class="internship-card-company"><span class="company-mark company-mark-small" aria-hidden="true"><?php if (!empty($item['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $item['company_id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($item['company_name'], 0, 1))) ?><?php endif; ?></span><span><?= h($item['company_name']) ?></span></div>
                            <h2><?= h($item['title']) ?></h2>
                            <p class="company-line"><?= h($item['location'] ? $item['location'] : 'Location not specified') ?></p>
                            <p class="description"><?= nl2br(h($item['description'])) ?></p>
                            <p class="deadline">Posted <?= h(\HiringCountdown::postedDate((string) $item['published_at'])) ?> · <?= (int) $item['hiring_duration_days'] ?> days · Deadline <?= h(date('M j, Y', strtotime((string) $item['application_deadline']))) ?></p>
                            <p class="hiring-countdown"><?= $itemHiringStatus['state'] === 'closed' ? 'Hiring closed' : (int) $itemHiringStatus['days_remaining'] . ' days remaining' ?></p>
                            <?php if ($user && $user['role'] === 'student' && $itemHiringStatus['can_apply']): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="internship_id" value="<?= (int) $item['id'] ?>"><?php $studentCredentials = db()->prepare('SELECT id, original_name, category FROM tbl_student_credentials WHERE user_id=? ORDER BY created_at DESC'); $studentCredentials->execute([(int) $user['id']]); $availableCredentials = $studentCredentials->fetchAll(); ?><details class="share-documents"><summary>Choose what to share with this company</summary><label class="checkbox-label"><input type="checkbox" name="share_biodata" value="1"> Share my e-Biodata for this application</label><?php foreach ($availableCredentials as $credential): ?><label class="checkbox-label"><input type="checkbox" name="credential_ids[]" value="<?= (int) $credential['id'] ?>"> <?= h(ucwords(str_replace('_', ' ', $credential['category']))) ?> · <?= h($credential['original_name']) ?></label><?php endforeach; ?></details><button class="button button-small" type="submit">Apply now <span aria-hidden="true">→</span></button></form>
                            <?php elseif ($user && $user['role'] === 'student'): ?><p class="muted">Applications are closed for this opportunity.</p>
                            <?php elseif (!$user && $itemHiringStatus['can_apply']): ?><a class="text-link" href="index.php?page=register">Create an account to apply →</a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($page === 'saved'): ?>
        <?php $savedQuery = db()->prepare("SELECT i.id, i.title, i.category, i.location, i.work_mode, i.slots, i.application_deadline, i.published_at, i.hiring_duration_days, i.status, s.saved_at, c.id AS company_id, c.name AS company_name, c.logo_key, CASE WHEN i.status='published' AND i.application_deadline>=CURDATE() AND c.verification_status='approved' THEN 1 ELSE 0 END AS is_available FROM tbl_saved_internships s JOIN tbl_internship_opportunities i ON i.id=s.internship_id JOIN tbl_companies c ON c.id=i.company_id WHERE s.student_id=? ORDER BY s.saved_at DESC"); $savedQuery->execute([(int) $user['id']]); $savedOpportunities = $savedQuery->fetchAll(); ?>
        <section class="page-heading"><p class="eyebrow">YOUR SHORTLIST</p><h1>Saved internships</h1><p class="muted">Your saved opportunities are linked to your account and available across devices.</p></section>
        <?php if (!$savedOpportunities): ?>
            <div class="empty-state">You haven’t saved any internships yet. <a class="text-link" href="index.php?page=explore">Explore current opportunities →</a></div>
        <?php else: ?>
            <div class="saved-opportunity-list">
                <?php foreach ($savedOpportunities as $savedOpportunity): ?>
                    <?php $savedHiringStatus = \HiringCountdown::status((string) $savedOpportunity['application_deadline'], (string) $savedOpportunity['status']); ?>
                    <article class="card saved-opportunity-card">
                        <div class="saved-opportunity-identity">
                            <span class="company-mark company-mark-small" aria-hidden="true"><?php if (!empty($savedOpportunity['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $savedOpportunity['company_id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($savedOpportunity['company_name'], 0, 1))) ?><?php endif; ?></span>
                            <div>
                            <span class="badge <?= h($savedHiringStatus['badge_class']) ?>"><?= h($savedHiringStatus['label']) ?></span>
                            <h2><?= h($savedOpportunity['title']) ?></h2>
                            <p><?= h($savedOpportunity['company_name']) ?> · <?= h($savedOpportunity['location'] ?: 'Location not listed') ?></p>
                            <small>Posted <?= h(\HiringCountdown::postedDate((string) $savedOpportunity['published_at'])) ?> · <?= (int) $savedOpportunity['hiring_duration_days'] ?> days · Deadline <?= h(date('M j, Y', strtotime((string) $savedOpportunity['application_deadline']))) ?></small>
                            <small>Saved <?= h(date('M j, Y', strtotime((string) $savedOpportunity['saved_at']))) ?></small>
                            </div>
                        </div>
                        <div class="saved-opportunity-actions">
                            <?php if ($savedOpportunity['status'] === 'published'): ?><a class="button button-small" href="index.php?page=explore&amp;internship_id=<?= (int) $savedOpportunity['id'] ?>">View opportunity</a><?php endif; ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unsave_internship"><input type="hidden" name="internship_id" value="<?= (int) $savedOpportunity['id'] ?>"><input type="hidden" name="return_page" value="saved"><button class="button button-small button-quiet" type="submit">Remove</button></form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($page === 'saved_companies'): ?>
        <?php $savedCompaniesQuery = db()->prepare("SELECT s.company_id, s.saved_at, c.name, c.industry, c.address, c.description, c.logo_key, c.verification_status, (SELECT COUNT(*) FROM tbl_internship_opportunities i WHERE i.company_id=c.id AND i.status='published' AND i.application_deadline>=CURDATE() AND c.verification_status='approved') AS opportunity_count FROM tbl_saved_companies s JOIN tbl_companies c ON c.id=s.company_id WHERE s.student_id=? ORDER BY s.saved_at DESC"); $savedCompaniesQuery->execute([(int) $user['id']]); $savedCompanies = $savedCompaniesQuery->fetchAll(); ?>
        <section class="page-heading"><p class="eyebrow">YOUR COMPANY SHORTLIST</p><h1>Interested companies</h1><p class="muted">Save employers to revisit their public profiles and current opportunities. Saving a company does not share your profile or submit an application.</p></section>
        <?php if (!$savedCompanies): ?><div class="empty-state">You haven’t saved any companies yet. <a class="text-link" href="index.php?page=explore">Explore approved companies →</a></div><?php else: ?>
            <div class="saved-opportunity-list"><?php foreach ($savedCompanies as $savedCompany): $isCompanyAvailable = $savedCompany['verification_status'] === 'approved'; ?>
                <article class="card saved-company-card">
                    <?php if ($isCompanyAvailable): ?><span class="company-mark"><?php if (!empty($savedCompany['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $savedCompany['company_id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($savedCompany['name'], 0, 1))) ?><?php endif; ?></span><?php endif; ?>
                    <div class="saved-company-copy"><span class="badge <?= $isCompanyAvailable ? 'published' : 'pending' ?>"><?= $isCompanyAvailable ? 'Verified company' : 'Profile unavailable' ?></span><h2><?= h($isCompanyAvailable ? $savedCompany['name'] : 'Company profile unavailable') ?></h2><?php if ($isCompanyAvailable): ?><p><?= h($savedCompany['industry'] ?: 'Industry not listed') ?><?= $savedCompany['address'] ? ' · ' . h($savedCompany['address']) : '' ?></p><p><?= h(mb_strimwidth((string) ($savedCompany['description'] ?? ''), 0, 220, '...')) ?></p><small><?= (int) $savedCompany['opportunity_count'] ?> open opportunit<?= (int) $savedCompany['opportunity_count'] === 1 ? 'y' : 'ies' ?></small><?php endif; ?><small>Saved <?= h(date('M j, Y', strtotime((string) $savedCompany['saved_at']))) ?></small></div>
                    <div class="saved-opportunity-actions"><?php if ($isCompanyAvailable): ?><a class="button button-small" href="index.php?page=explore&amp;company_id=<?= (int) $savedCompany['company_id'] ?>">View company</a><?php endif; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unsave_company"><input type="hidden" name="company_id" value="<?= (int) $savedCompany['company_id'] ?>"><input type="hidden" name="return_page" value="saved_companies"><button class="button button-small button-quiet" type="submit">Remove</button></form></div>
                </article>
            <?php endforeach; ?></div>
        <?php endif; ?>

    <?php elseif ($page === 'placements'): ?>
        <?php $coordinatorQuery = db()->prepare("SELECT institution_id FROM tbl_ojt_coordinators WHERE user_id=? AND status='active'"); $coordinatorQuery->execute([(int) $user['id']]); $coordinatorInstitutionId = (int) $coordinatorQuery->fetchColumn(); $placementQuery = db()->prepare("SELECT p.id, p.status, p.start_date, p.end_date, p.required_hours, p.completed_hours, a.id AS application_id, a.student_id, u.full_name AS student_name, u.email AS student_email, sp.program, i.title, c.name AS company_name FROM tbl_placements p JOIN tbl_applications a ON a.id=p.application_id JOIN tbl_student_profiles sp ON sp.user_id=a.student_id JOIN tbl_users u ON u.id=a.student_id JOIN tbl_internship_opportunities i ON i.id=a.internship_id JOIN tbl_companies c ON c.id=i.company_id WHERE sp.institution_id=? AND p.status IN ('pending','active') ORDER BY FIELD(p.status,'pending','active'), p.created_at DESC"); $placementQuery->execute([$coordinatorInstitutionId]); $placements = $placementQuery->fetchAll(); ?>
        <section id="placement-approvals" class="page-heading"><p class="eyebrow">INSTITUTIONAL WORKSPACE</p><h1>Placement approvals</h1><p class="muted">Review company-accepted applications from students in your assigned institution and activate approved placements.</p></section>
        <?php if (!$placements): ?><div class="empty-state">No pending or active placements for your institution.</div><?php else: ?>
            <div id="student-placement-monitoring" class="saved-opportunity-list"><?php foreach ($placements as $placement): ?>
                <article class="card placement-card">
                    <div class="placement-card-heading"><div><span class="badge <?= h($placement['status']) ?>"><?= h(ucfirst($placement['status'])) ?></span><h2><?= h($placement['title']) ?></h2><p><?= h($placement['student_name']) ?> · <?= h($placement['program'] ?: 'Program not provided') ?></p><small><?= h($placement['company_name']) ?> · <?= h($placement['student_email']) ?></small></div><a class="text-link" href="index.php?page=messages">Open messages</a></div>
                    <?php if ($placement['status'] === 'pending'): ?><form method="post" class="placement-approval-form"><?= csrf_field() ?><input type="hidden" name="action" value="approve_placement"><input type="hidden" name="placement_id" value="<?= (int) $placement['id'] ?>"><label>Placement start<input type="date" name="start_date" min="<?= h(date('Y-m-d')) ?>" required></label><label>Placement end<input type="date" name="end_date" required></label><label>Required OJT hours<input type="number" name="required_hours" min="1" max="65535" required></label><button class="button button-small" type="submit">Approve and activate</button></form>
                    <?php else: ?><p class="muted">Active · <?= h((string) $placement['start_date']) ?> to <?= h((string) $placement['end_date']) ?> · <?= (int) $placement['completed_hours'] ?>/<?= (int) $placement['required_hours'] ?> hours completed</p><?php endif; ?>
                </article>
            <?php endforeach; ?></div>
        <?php endif; ?>

    <?php elseif ($page === 'applications'): ?>
        <?php if ($user['role'] === 'student') { $stmt = db()->prepare('SELECT a.id, a.student_id, a.status, a.interview_at, a.created_at, a.cover_letter, i.title, i.location, c.id AS company_id, c.name AS company_name, c.logo_key FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id JOIN tbl_companies c ON c.id=i.company_id WHERE a.student_id=? ORDER BY a.created_at DESC'); $stmt->execute([(int) $user['id']]); $applications = $stmt->fetchAll(); } else { $companyId = current_company_id((int) $user['id']); $stmt = db()->prepare("SELECT a.id, a.student_id, a.status, a.interview_at, a.created_at, a.cover_letter, i.title, u.full_name AS student_name, u.email AS student_email, sp.program, (SELECT qr.assessment FROM tbl_application_qualification_reviews qr WHERE qr.application_id=a.id ORDER BY qr.id DESC LIMIT 1) AS qualification_assessment, (EXISTS (SELECT 1 FROM tbl_application_biodata ab WHERE ab.application_id=a.id AND ab.student_id=a.student_id) OR EXISTS (SELECT 1 FROM tbl_application_documents ad WHERE ad.application_id=a.id)) AS has_review_documents FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id JOIN tbl_users u ON u.id=a.student_id LEFT JOIN tbl_student_profiles sp ON sp.user_id=u.id WHERE i.company_id=? ORDER BY a.created_at DESC"); $stmt->execute([$companyId]); $applications = $stmt->fetchAll(); } ?>
        <section class="page-heading"><p class="eyebrow"><?= $user['role'] === 'student' ? 'YOUR PROGRESS' : 'CANDIDATE PIPELINE' ?></p><h1><?= $user['role'] === 'student' ? 'My applications' : 'Applications received' ?></h1><p class="muted"><?= $user['role'] === 'student' ? 'Stay up to date as employers review your applications.' : 'Review applications submitted to your company.' ?></p></section>
        <?php if (!$applications): ?>
            <div class="empty-state"><?= $user['role'] === 'student' ? 'You have not applied to an opportunity yet.' : 'Your internships have no applicants yet.' ?></div>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Opportunity</th><th><?= $user['role'] === 'student' ? 'Company' : 'Student' ?></th><th>Status</th><th>Applied</th><th>Shared information</th><?php if ($user['role'] === 'employer'): ?><th>Qualification review</th><th>Action</th><?php endif; ?></tr></thead>
                <tbody><?php foreach ($applications as $application): ?>
                    <?php $sharedQuery = db()->prepare("SELECT s.id, s.original_name, 'snapshot' AS document_kind FROM tbl_application_document_snapshots s WHERE s.application_id=? UNION ALL SELECT c.id, c.original_name, 'credential' AS document_kind FROM tbl_application_documents ad JOIN tbl_student_credentials c ON c.id=ad.credential_id WHERE ad.application_id=? AND NOT EXISTS (SELECT 1 FROM tbl_application_document_snapshots s WHERE s.application_id=ad.application_id AND s.credential_id=ad.credential_id) ORDER BY original_name"); $sharedQuery->execute([(int) $application['id'], (int) $application['id']]); $sharedDocuments = $sharedQuery->fetchAll(); ?>
                    <tr>
                        <td><strong><?= h($application['title']) ?></strong><?php if ($user['role'] === 'employer'): ?><small><?= h($application['program'] ?? 'Program not provided') ?></small><?php endif; ?><?php if (!empty($application['cover_letter'])): ?><details class="application-history"><summary><?= $user['role'] === 'student' ? 'My application letter' : 'Application letter' ?></summary><p><?= nl2br(h($application['cover_letter'])) ?></p></details><?php endif; ?><?php $timelineQuery = db()->prepare('SELECT h.status, h.note, h.created_at, u.full_name FROM tbl_application_status_history h JOIN tbl_users u ON u.id=h.changed_by WHERE h.application_id=? ORDER BY h.created_at DESC, h.id DESC'); $timelineQuery->execute([(int) $application['id']]); $timeline = $timelineQuery->fetchAll(); ?><details class="application-history"><summary>Application history</summary><?php if ($timeline): ?><ol><?php foreach ($timeline as $entry): ?><li><strong><?= h(ucfirst($entry['status'])) ?></strong><span><?= h(date('M j, Y H:i', strtotime($entry['created_at']))) ?> · <?= h($entry['full_name']) ?></span><?php if ($entry['note']): ?><p><?= h($entry['note']) ?></p><?php endif; ?></li><?php endforeach; ?></ol><?php else: ?><span class="muted">No status updates yet.</span><?php endif; ?></details></td>
                        <td><?php if ($user['role'] === 'student'): ?><span class="application-company-identity"><span class="company-mark company-mark-small" aria-hidden="true"><?php if (!empty($application['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $application['company_id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($application['company_name'], 0, 1))) ?><?php endif; ?></span><?= h($application['company_name']) ?></span><?php else: ?><?= h($application['student_name']) . '<small>' . h($application['student_email']) . '</small>' ?><?php endif; ?></td>
                        <td><span class="badge <?= h($application['status']) ?>"><?= h(ucwords(str_replace('_', ' ', $application['status']))) ?></span><?php if (!empty($application['interview_at'])): ?><small>Interview: <?= h(date('M j, Y H:i', strtotime($application['interview_at']))) ?></small><?php endif; ?><?php if ($user['role'] === 'student' && in_array($application['status'], ['submitted', 'reviewing', 'shortlisted', 'interview_scheduled'], true)): ?><form method="post" class="inline-form" onsubmit="return confirm('Withdraw this application?');"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw_application"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><button class="text-button" type="submit">Withdraw</button></form><?php endif; ?></td>
                        <td><?= h(date('M j, Y', strtotime($application['created_at']))) ?></td>
                        <td><?php $sharedBio = db()->prepare('SELECT COUNT(*) FROM tbl_application_biodata WHERE application_id=? AND student_id=?'); $sharedBio->execute([(int) $application['id'], (int) $application['student_id']]); $hasSharedBiodata = (int) $sharedBio->fetchColumn() > 0; ?><?php if ($hasSharedBiodata && $user['role'] === 'employer'): ?><a class="text-link document-link" href="index.php?page=applicant_biodata&amp;id=<?= (int) $application['id'] ?>">View shared e-Biodata</a><?php elseif ($user['role'] === 'student'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="update_biodata_sharing"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><button class="text-button" name="decision" value="<?= $hasSharedBiodata ? 'revoke' : 'share' ?>"><?= $hasSharedBiodata ? 'Revoke e-Biodata share' : 'Share e-Biodata' ?></button></form><?php elseif (!$hasSharedBiodata): ?><span class="muted">None shared</span><?php endif; ?><?php if ($sharedDocuments): foreach ($sharedDocuments as $sharedDocument): ?><a class="text-link document-link" href="index.php?page=<?= $sharedDocument['document_kind'] === 'snapshot' ? 'application_document' : 'credential_download' ?>&amp;id=<?= (int) $sharedDocument['id'] ?>"><?= h($sharedDocument['original_name']) ?></a><?php endforeach; elseif (!$hasSharedBiodata): ?><span class="muted">No documents shared</span><?php endif; ?></td>
                        <?php if ($user['role'] === 'employer'): ?>
                            <td>
                                <?php if (!empty($application['qualification_assessment'])): ?><span class="badge <?= h($application['qualification_assessment']) ?>"><?= h(ucwords(str_replace('_', ' ', $application['qualification_assessment']))) ?></span><?php else: ?><span class="muted">Not reviewed</span><?php endif; ?>
                                <?php if (!empty($application['has_review_documents'])): ?>
                                    <form method="post" class="qualification-review-form"><?= csrf_field() ?><input type="hidden" name="action" value="review_qualifications"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><label>Assessment<select name="assessment" required><option value="">Select assessment</option><option value="qualified">Qualified</option><option value="needs_improvement">Needs improvement</option><option value="not_qualified">Not qualified</option></select></label><label>Review notes<textarea name="notes" maxlength="1000" required placeholder="Summarize the qualification review"></textarea></label><label>Missing qualifications <span class="field-help">Optional</span><textarea name="missing_qualifications" maxlength="3000"></textarea></label><button class="button button-small" type="submit">Record review</button></form>
                                <?php else: ?><p class="muted">Waiting for the applicant to share an e-Biodata or resume.</p><?php endif; ?>
                            </td>
                            <td><form method="post" class="inline-review"><?= csrf_field() ?><input type="hidden" name="action" value="update_application"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><select name="status" aria-label="Application status"><option value="reviewing">Reviewing</option><option value="shortlisted">Shortlist</option><option value="interview_scheduled">Schedule interview</option><option value="accepted" <?= ($application['qualification_assessment'] ?? '') !== 'qualified' ? 'disabled' : '' ?>>Accept</option><option value="rejected">Reject</option></select><label class="sr-only" for="interview-at-<?= (int) $application['id'] ?>">Interview time</label><input id="interview-at-<?= (int) $application['id'] ?>" type="datetime-local" name="interview_at" aria-label="Interview date and time"><label class="sr-only" for="interview-location-<?= (int) $application['id'] ?>">Interview location or meeting link</label><input id="interview-location-<?= (int) $application['id'] ?>" type="text" name="interview_location" maxlength="255" placeholder="Location or HTTPS meeting link"><button class="button button-small" type="submit">Save</button><a class="text-link" href="index.php?page=messages">Message</a></form></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>

    <?php elseif ($page === 'applicant_biodata'): ?>
        <section class="page-heading"><p class="eyebrow">SHARED FOR THIS APPLICATION</p><h1><?= h($applicationBiodata['full_name']) ?></h1><p class="muted">The student explicitly shared these biodata sections for an application to your company.</p></section>
        <?php $data = $applicationBiodata; $visibility = $data['visibility']; ?><section class="biodata-preview <?= ($data['template'] ?? 'standard') === 'modern' ? 'modern' : '' ?>"><div class="biodata-content"><?php require __DIR__ . '/biodata_content.php'; ?></div></section>
    <?php elseif (in_array($page, ['biodata', 'credentials', 'training', 'messages', 'email', 'account_settings'], true)): ?>
        <?php require __DIR__ . '/enhanced.php'; ?>
    <?php elseif ($page === 'verify_email'): ?>
        <?php require dirname(__DIR__) . '/Modules/AccountVerification/Views/verify_email.php'; ?>
    <?php elseif ($page === 'verify_phone'): ?>
        <?php require dirname(__DIR__) . '/Modules/AccountVerification/Views/verify_phone.php'; ?>
    <?php elseif ($page === 'notifications'): ?>
        <?php $notificationQuery = db()->prepare('SELECT id, title, message, related_type, related_id, read_at, created_at FROM tbl_notifications WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 100'); $notificationQuery->execute([(int) $user['id']]); $notificationRows = $notificationQuery->fetchAll(); ?>
        <section class="page-heading"><p class="eyebrow">YOUR UPDATES</p><h1>Notifications</h1><p class="muted">Application, verification, training, and message updates for your account.</p></section>
        <?php if ($notificationRows): ?>
            <form method="post" class="notification-actions"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_notifications_read"><button class="button button-small button-quiet" type="submit">Mark all as read</button></form>
            <div class="notification-list">
                <?php foreach ($notificationRows as $notification): ?>
                    <?php $notificationUrl = match ($notification['related_type']) {
                        'application' => 'index.php?page=applications' . ($notification['related_id'] ? '&application_id=' . (int) $notification['related_id'] : ''),
                        'conversation' => 'index.php?page=messages' . ($notification['related_id'] ? '&conversation_id=' . (int) $notification['related_id'] : ''),
                        'company' => $user['role'] === 'admin' ? 'index.php?page=admin_companies' : 'index.php?page=company_verification',
                        'security' => $user['role'] === 'admin' ? 'index.php?page=admin_audit' : 'index.php?page=notifications',
                        'training' => 'index.php?page=training',
                        'placement' => 'index.php?page=dashboard',
                        default => 'index.php?page=notifications',
                    }; ?>
                    <article class="notification-item <?= $notification['read_at'] ? '' : 'unread' ?>">
                        <a href="<?= h($notificationUrl) ?>" data-notification-id="<?= (int) $notification['id'] ?>" data-unread="<?= $notification['read_at'] ? '0' : '1' ?>"><strong><?= h($notification['title']) ?></strong><p><?= nl2br(h($notification['message'])) ?></p><small><?= h(date('M j, Y H:i', strtotime($notification['created_at']))) ?></small></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?><div class="empty-state">You’re all caught up. New updates will appear here.</div><?php endif; ?>

    <?php elseif ($page === 'verification_success'): ?>
        <?php require dirname(__DIR__) . '/Modules/AccountVerification/Views/verification_success.php'; ?>
    <?php elseif ($page === 'verification_admin'): ?>
        <?php require dirname(__DIR__) . '/Modules/AccountVerification/Views/verification_admin.php'; ?>

    <?php elseif ($page === 'forbidden'): ?><section class="card narrow"><p class="eyebrow">ACCESS RESTRICTED</p><h1>You can’t access this page</h1><p>Your account does not have permission to view this area.</p><a class="button" href="index.php?page=dashboard">Return to dashboard</a></section>
    <?php else: ?><section class="card narrow"><p class="eyebrow">SMARTJOB</p><h1>Page not found</h1><p>The page you requested does not exist.</p><a class="button" href="index.php">Go home</a></section><?php endif; ?>
</main>
<footer class="footer"><div><a class="brand footer-brand" href="index.php"><span class="brand-mark">S</span><span>SMART<span class="brand-accent">JOB</span><small>OJT placement platform</small></span></a></div><span>Helping potential find its place.</span><span>© <?= date('Y') ?> SMARTJOB</span></footer>
<?php if ($user && in_array($user['role'], ['student', 'employer'], true)): ?>
<dialog class="ai-writing-dialog" data-ai-writing-dialog aria-labelledby="ai-writing-heading">
    <div class="ai-writing-dialog-content">
        <header class="ai-writing-header"><div><p class="eyebrow"><i class="bi bi-stars" aria-hidden="true"></i> SMARTJOB WRITING ASSISTANT</p><h2 id="ai-writing-heading" data-ai-writing-title>Write with AI</h2></div><button class="ai-writing-close" type="button" data-ai-cancel aria-label="Close writing assistant">&times;</button></header>
        <p class="muted ai-writing-disclosure">With your consent, this sends the selected text and relevant form context to SMARTJOB's configured AI provider. Employer requests may also include your company profile details; internship requests include the opportunity details in its form. Optional details below are included only if you enter them. Do not send sensitive or confidential information. AI drafts can be inaccurate: review every claim. Nothing is inserted, saved, or published unless you choose <strong>Use This Text</strong> and then submit the existing form.</p>
        <label>Writing task<select data-ai-writing-task></select></label>
        <div class="form-grid ai-writing-settings">
            <label>Action<select data-ai-writing-action><option value="generate">Generate from available details</option><option value="improve">Improve writing</option><option value="professional">Make professional</option><option value="grammar">Correct grammar</option><option value="shorten">Shorten text</option><option value="expand">Expand text</option><option value="regenerate">Regenerate</option></select></label>
            <label>Tone<select data-ai-writing-tone><option value="professional">Professional</option><option value="simple">Simple and clear</option><option value="friendly">Friendly</option><option value="formal">Formal</option><option value="technical">Technical</option><option value="recruitment">Recruitment-focused</option></select></label>
        </div>
        <label>Optional factual details <span class="field-help">Do not include personal or confidential information</span><textarea data-ai-extra-context maxlength="1000" rows="3" placeholder="Optional factual details. Do not enter passwords, contact details, private messages, or confidential documents."></textarea></label>
        <label class="checkbox-label ai-writing-consent"><input type="checkbox" data-ai-consent> I consent to send the selected text, relevant form context, and any optional details I entered to SMARTJOB's configured AI provider.</label>
        <button class="button" type="button" data-ai-generate><i class="bi bi-magic" aria-hidden="true"></i> Generate draft</button>
        <p class="ai-writing-status" data-ai-status aria-live="polite"></p>
        <section class="ai-writing-preview" data-ai-preview hidden>
            <label>Generated draft<textarea data-ai-result rows="9" readonly></textarea></label>
            <div class="ai-writing-actions"><button class="button button-small button-quiet" type="button" data-ai-copy><i class="bi bi-clipboard" aria-hidden="true"></i> Copy</button><button class="button button-small button-quiet" type="button" data-ai-regenerate><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Regenerate</button><button class="button button-small" type="button" data-ai-use><i class="bi bi-check-circle" aria-hidden="true"></i> Use This Text</button><button class="button button-small button-quiet" type="button" data-ai-cancel>Cancel</button></div>
        </section>
    </div>
</dialog>
<?php endif; ?>
<script>
const navToggle = document.querySelector('.nav-toggle');
const mainNavigation = document.getElementById('main-navigation');
if (navToggle && mainNavigation) {
    const closeNavigation = () => {
        navToggle.setAttribute('aria-expanded', 'false');
        mainNavigation.classList.remove('is-open');
    };
    navToggle.addEventListener('click', () => {
        const isOpen = navToggle.getAttribute('aria-expanded') === 'true';
        navToggle.setAttribute('aria-expanded', String(!isOpen));
        mainNavigation.classList.toggle('is-open', !isOpen);
    });
    mainNavigation.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeNavigation();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeNavigation();
    });
}
const dropdownPanels = Array.from(document.querySelectorAll('[data-dropdown-panel]'));
const closeDropdownPanels = (except = null) => {
    for (const panel of dropdownPanels) {
        if (panel === except || !panel.open) continue;
        panel.open = false;
        const menu = panel.querySelector('.nav-menu');
        if (menu) {
            menu.classList.remove('is-viewport-positioned');
            menu.removeAttribute('style');
        }
    }
};
const accountDropdown = document.querySelector('.nav-account[data-dropdown-panel]');
const updateAccountMenuActiveLink = () => {
    if (!accountDropdown) return;
    const currentPage = new URLSearchParams(window.location.search).get('page') || 'home';
    const currentFragment = window.location.hash.slice(1);
    for (const link of accountDropdown.querySelectorAll('[data-account-page]')) {
        const isCurrent = link.dataset.accountPage === currentPage
            && (link.dataset.accountFragment || '') === currentFragment;
        link.classList.toggle('is-active', isCurrent);
        if (isCurrent) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    }
};
const positionAccountMenu = () => {
    if (!accountDropdown?.open) return;
    const summary = accountDropdown.querySelector(':scope > summary');
    const menu = accountDropdown.querySelector(':scope > .nav-menu');
    if (!summary || !menu) return;
    const anchor = summary.getBoundingClientRect();
    const margin = 12;
    const menuWidth = Math.min(310, window.innerWidth - margin * 2);
    const heightLimit = Math.min(560, window.innerHeight - margin * 2);
    const spaceBelow = window.innerHeight - anchor.bottom - 8 - margin;
    const spaceAbove = anchor.top - 8 - margin;
    const openAbove = spaceBelow < Math.min(menu.scrollHeight, heightLimit) && spaceAbove > spaceBelow;
    const availableHeight = Math.max(100, Math.min(heightLimit, openAbove ? spaceAbove : spaceBelow));
    const left = Math.max(margin, Math.min(anchor.right - menuWidth, window.innerWidth - menuWidth - margin));
    const top = openAbove
        ? Math.max(margin, anchor.top - availableHeight - 8)
        : Math.max(margin, Math.min(anchor.bottom + 8, window.innerHeight - availableHeight - margin));
    menu.classList.add('is-viewport-positioned');
    menu.style.width = `${menuWidth}px`;
    menu.style.maxHeight = `${availableHeight}px`;
    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
};
for (const panel of dropdownPanels) {
    panel.addEventListener('toggle', () => {
        if (panel.open) {
            closeDropdownPanels(panel);
            if (panel === accountDropdown) window.requestAnimationFrame(positionAccountMenu);
        } else {
            const menu = panel.querySelector('.nav-menu');
            if (menu) {
                menu.classList.remove('is-viewport-positioned');
                menu.removeAttribute('style');
            }
        }
    });
    panel.addEventListener('keydown', (event) => {
        const summary = panel.querySelector(':scope > summary');
        const menu = panel.querySelector(':scope > .nav-menu, :scope > .activity-menu');
        if (!summary || !menu) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            panel.open = false;
            if (panel === accountDropdown && navToggle?.getAttribute('aria-expanded') === 'true') navToggle.focus();
            else summary.focus();
            return;
        }
        const items = Array.from(menu.querySelectorAll('a[href], button:not(:disabled)'));
        if (!items.length) return;
        if (event.target === summary && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            items[event.key === 'ArrowDown' ? 0 : items.length - 1].focus();
            return;
        }
        const currentIndex = items.indexOf(event.target);
        if (currentIndex < 0) return;
        let nextIndex = null;
        if (event.key === 'ArrowDown') nextIndex = (currentIndex + 1) % items.length;
        else if (event.key === 'ArrowUp') nextIndex = (currentIndex - 1 + items.length) % items.length;
        else if (event.key === 'Home') nextIndex = 0;
        else if (event.key === 'End') nextIndex = items.length - 1;
        if (nextIndex !== null) {
            event.preventDefault();
            items[nextIndex].focus();
        }
    });
}
document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-dropdown-panel]')) closeDropdownPanels();
    if (event.target.closest('.nav-account .account-menu-item')) closeDropdownPanels();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeDropdownPanels();
});
window.addEventListener('resize', positionAccountMenu);
window.addEventListener('scroll', positionAccountMenu, true);
window.addEventListener('hashchange', updateAccountMenuActiveLink);
updateAccountMenuActiveLink();
const verificationPolicyForm = document.getElementById('verification-policy-form');
if (verificationPolicyForm) {
    const policyTabs = Array.from(verificationPolicyForm.querySelectorAll('[data-policy-tab]'));
    const policyPanels = Array.from(verificationPolicyForm.querySelectorAll('[data-policy-panel]'));
    const searchInput = verificationPolicyForm.querySelector('[data-policy-search]');
    const statusFilter = verificationPolicyForm.querySelector('[data-policy-filter]');
    const saveButton = verificationPolicyForm.querySelector('[data-policy-save]');
    const resetButton = verificationPolicyForm.querySelector('[data-policy-reset]');
    const dirtyLabel = verificationPolicyForm.querySelector('[data-policy-dirty]');
    const feedback = verificationPolicyForm.querySelector('[data-policy-feedback]');
    let activeType = policyTabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.policyTab;
    let savedState;
    let saving = false;
    const controls = () => Array.from(verificationPolicyForm.elements);
    const currentState = () => controls().map((control) => [
        control.name,
        control.type,
        control.value,
        control.type === 'radio' ? control.checked : null
    ]);
    const hasUnsavedChanges = () => JSON.stringify(currentState()) !== JSON.stringify(savedState);
    const updateDirtyState = () => {
        const dirty = hasUnsavedChanges();
        dirtyLabel.hidden = !dirty;
        saveButton.disabled = !dirty || saving;
        resetButton.disabled = !dirty || saving;
    };
    const activePanel = () => policyPanels.find((panel) => panel.dataset.policyPanel === activeType);
    const updateSummary = () => {
        const panel = activePanel();
        if (!panel) return;
        const rows = Array.from(panel.querySelectorAll('[data-policy-row]'));
        const counts = {required: 0, optional: 0, not_applicable: 0};
        for (const row of rows) {
            const selected = row.querySelector('input[type="radio"]:checked')
                || row.querySelector('input[type="hidden"]');
            const status = selected?.value || 'optional';
            row.dataset.status = status;
            counts[status] = (counts[status] || 0) + 1;
        }
        verificationPolicyForm.querySelector('[data-policy-summary-type]').textContent =
            policyTabs.find((tab) => tab.dataset.policyTab === activeType)?.textContent.trim() || '';
        verificationPolicyForm.querySelector('[data-count-total]').textContent = String(rows.length);
        verificationPolicyForm.querySelector('[data-count-required]').textContent = String(counts.required);
        verificationPolicyForm.querySelector('[data-count-optional]').textContent = String(counts.optional);
        verificationPolicyForm.querySelector('[data-count-na]').textContent = String(counts.not_applicable);
    };
    const applyFilters = () => {
        const panel = activePanel();
        if (!panel) return;
        const query = searchInput.value.trim().toLocaleLowerCase();
        const selectedStatus = statusFilter.value;
        const rows = Array.from(panel.querySelectorAll('[data-policy-row]'));
        for (const row of rows) {
            const matchesSearch = row.dataset.search.includes(query);
            const matchesStatus = selectedStatus === 'all' || row.dataset.status === selectedStatus;
            row.hidden = !matchesSearch || !matchesStatus;
        }
        for (const section of panel.querySelectorAll('[data-policy-section]')) {
            section.hidden = !section.querySelector('[data-policy-row]:not([hidden])');
        }
        panel.querySelector('[data-policy-no-results]').hidden = rows.some((row) => !row.hidden);
    };
    const selectType = (type, focus = false) => {
        activeType = type;
        for (const tab of policyTabs) {
            const selected = tab.dataset.policyTab === type;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) tab.focus();
        }
        for (const panel of policyPanels) panel.hidden = panel.dataset.policyPanel !== type;
        updateSummary();
        applyFilters();
    };
    const setFeedback = (message, isError = false) => {
        feedback.textContent = message;
        feedback.classList.toggle('error', isError);
        feedback.hidden = !message;
    };
    policyTabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectType(tab.dataset.policyTab));
        tab.addEventListener('keydown', (event) => {
            let nextIndex = index;
            if (event.key === 'ArrowRight') nextIndex = (index + 1) % policyTabs.length;
            else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + policyTabs.length) % policyTabs.length;
            else if (event.key === 'Home') nextIndex = 0;
            else if (event.key === 'End') nextIndex = policyTabs.length - 1;
            else return;
            event.preventDefault();
            selectType(policyTabs[nextIndex].dataset.policyTab, true);
        });
    });
    searchInput.addEventListener('input', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    verificationPolicyForm.addEventListener('input', (event) => {
        if (event.target.matches('input[type="radio"]')) {
            updateSummary();
            applyFilters();
        }
        updateDirtyState();
    });
    verificationPolicyForm.addEventListener('change', updateDirtyState);
    resetButton.addEventListener('click', () => {
        const savedControls = savedState;
        controls().forEach((control, index) => {
            const saved = savedControls[index];
            if (!saved) return;
            if (control.type === 'radio') control.checked = saved[3] === true;
            else if (control.type !== 'submit' && control.type !== 'button') control.value = saved[2];
        });
        setFeedback('');
        updateSummary();
        applyFilters();
        updateDirtyState();
    });
    verificationPolicyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (saving || !hasUnsavedChanges()) return;
        saving = true;
        saveButton.disabled = true;
        resetButton.disabled = true;
        saveButton.innerHTML = '<i class="bi bi-arrow-repeat" aria-hidden="true"></i> Saving...';
        setFeedback('');
        try {
            const response = await fetch(verificationPolicyForm.getAttribute('action') || window.location.href, {
                method: 'POST',
                headers: {'Accept': 'application/json'},
                body: new FormData(verificationPolicyForm)
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Verification requirements could not be saved.');
            savedState = currentState();
            setFeedback(result.notice || 'Verification requirements saved.');
            updateDirtyState();
        } catch (error) {
            setFeedback(error instanceof Error ? error.message : 'Verification requirements could not be saved. Please try again.', true);
        } finally {
            saving = false;
            saveButton.innerHTML = '<i class="bi bi-check2-circle" aria-hidden="true"></i> Save Changes';
            updateDirtyState();
        }
    });
    window.addEventListener('beforeunload', (event) => {
        if (!hasUnsavedChanges()) return;
        event.preventDefault();
        event.returnValue = '';
    });
    savedState = currentState();
    selectType(activeType || policyTabs[0]?.dataset.policyTab);
    updateDirtyState();
}
const companyUploadForm = document.getElementById('company-documents-form');
if (companyUploadForm) {
    const fileInputs = Array.from(companyUploadForm.querySelectorAll('[data-file-picker]'));
    const preview = companyUploadForm.querySelector('[data-upload-preview]');
    const countHeading = companyUploadForm.querySelector('[data-upload-count]');
    const emptyMessage = companyUploadForm.querySelector('[data-upload-empty]');
    const errorMessage = companyUploadForm.querySelector('[data-upload-error]');
    const successMessage = companyUploadForm.querySelector('[data-upload-success]');
    const progressWrap = companyUploadForm.querySelector('[data-upload-progress]');
    const progressBar = companyUploadForm.querySelector('[data-progress-bar]');
    const progressLabel = companyUploadForm.querySelector('[data-progress-label]');
    const submitButton = companyUploadForm.querySelector('[data-upload-submit]');
    const selectedFiles = [];
    let selectionId = 0;
    let selectionError = '';
    const formatBytes = (bytes) => bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.ceil(bytes / 1024))} KB`;
    const setError = (message) => {
        errorMessage.textContent = message;
        errorMessage.hidden = !message;
    };
    const renderPreview = () => {
        preview.replaceChildren();
        let totalBytes = 0;
        for (const selection of selectedFiles) {
            totalBytes += selection.file.size;
            const row = document.createElement('li');
            row.className = 'verification-preview-item';
            const details = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = selection.file.name;
            const summary = document.createElement('small');
            const fileType = selection.file.type || (selection.file.name.split('.').pop() || 'File').toUpperCase();
            summary.textContent = `${selection.documentLabel} · ${fileType} · ${formatBytes(selection.file.size)}`;
            details.append(name, summary);
            const actions = document.createElement('div');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button button-small button-quiet';
            remove.textContent = 'Remove';
            remove.setAttribute('aria-label', `Remove ${selection.file.name}`);
            remove.addEventListener('click', () => {
                const index = selectedFiles.findIndex((entry) => entry.id === selection.id);
                if (index >= 0) selectedFiles.splice(index, 1);
                selectionError = '';
                renderPreview();
            });
            const replace = document.createElement('button');
            replace.type = 'button';
            replace.className = 'button button-small button-quiet';
            replace.textContent = 'Replace';
            replace.addEventListener('click', () => selection.input.click());
            actions.append(remove, replace);
            row.append(details, actions);
            preview.append(row);
        }
        countHeading.textContent = `${selectedFiles.length} document${selectedFiles.length === 1 ? '' : 's'} ready to upload`;
        emptyMessage.hidden = selectedFiles.length > 0;
        if (selectionError) {
            setError(selectionError);
        } else if (totalBytes > Number(companyUploadForm.dataset.maxBatchBytes)) {
            setError(`The selected documents exceed the total batch limit of ${formatBytes(Number(companyUploadForm.dataset.maxBatchBytes))}.`);
        } else if (selectedFiles.some((selection) => selection.file.size > Number(companyUploadForm.dataset.maxFileBytes))) {
            setError(`Each document must be no larger than ${formatBytes(Number(companyUploadForm.dataset.maxFileBytes))}.`);
        } else {
            setError('');
        }
    };
    const addSelection = (input, fileList) => {
        for (let index = selectedFiles.length - 1; index >= 0; index--) {
            if (selectedFiles[index].input === input) selectedFiles.splice(index, 1);
        }
        const files = Array.from(fileList);
        if (input.dataset.replacementId && files.length > 1) {
            selectionError = 'Choose one replacement file for each returned document.';
            input.value = '';
            renderPreview();
            return;
        }
        const allowedExtensions = /\.(pdf|docx|jpe?g|png)$/i;
        for (const file of files) {
            if (file.size < 1) {
                selectionError = `${file.name} is empty. Choose a non-empty document.`;
                input.value = '';
                renderPreview();
                return;
            }
            if (!allowedExtensions.test(file.name)) {
                selectionError = `${file.name} is not a supported PDF, DOCX, JPG, JPEG, or PNG file.`;
                input.value = '';
                renderPreview();
                return;
            }
            selectedFiles.push({
                id: ++selectionId,
                file,
                input,
                documentType: input.dataset.documentType || '',
                previousDocumentId: input.dataset.replacementId || '',
                documentLabel: input.dataset.documentLabel || 'Verification document',
            });
        }
        selectionError = '';
        renderPreview();
    };
    for (const input of fileInputs) {
        input.addEventListener('change', () => addSelection(input, input.files));
        const dropzone = input.closest('[data-upload-drop], [data-replacement-drop]');
        if (dropzone) {
            for (const eventName of ['dragenter', 'dragover']) {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.add('drag-over');
                });
            }
            for (const eventName of ['dragleave', 'drop']) {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.remove('drag-over');
                    if (eventName === 'drop' && event.dataTransfer?.files.length) {
                        addSelection(input, event.dataTransfer.files);
                    }
                });
            }
        }
    }
    companyUploadForm.addEventListener('submit', (event) => {
        event.preventDefault();
        setError('');
        if (selectionError) {
            setError(selectionError);
            return;
        }
        if (!selectedFiles.length) {
            setError('Select at least one verification document before submitting.');
            return;
        }
        const totalBytes = selectedFiles.reduce((sum, selection) => sum + selection.file.size, 0);
        if (selectedFiles.some((selection) => selection.file.size > Number(companyUploadForm.dataset.maxFileBytes))
            || totalBytes > Number(companyUploadForm.dataset.maxBatchBytes)) {
            renderPreview();
            return;
        }
        const count = selectedFiles.length;
        if (!window.confirm(`Submit all ${count} selected document${count === 1 ? '' : 's'} for administrator review?`)) return;
        const body = new FormData(companyUploadForm);
        for (const input of fileInputs) body.delete(input.name);
        for (const selection of selectedFiles) {
            const field = selection.previousDocumentId
                ? `replacements[${selection.previousDocumentId}]`
                : `documents[${selection.documentType}][]`;
            body.append(field, selection.file, selection.file.name);
        }
        const request = new XMLHttpRequest();
        request.open('POST', companyUploadForm.getAttribute('action') || window.location.href);
        request.setRequestHeader('Accept', 'application/json');
        submitButton.disabled = true;
        submitButton.textContent = 'Uploading documents…';
        progressWrap.hidden = false;
        progressBar.value = 0;
        progressLabel.textContent = 'Preparing upload…';
        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                progressLabel.textContent = 'Uploading documents…';
                return;
            }
            const percentage = Math.round(progressEvent.loaded / progressEvent.total * 100);
            progressBar.value = percentage;
            progressLabel.textContent = `Uploading… ${percentage}%`;
        });
        request.addEventListener('load', () => {
            let response;
            try {
                response = JSON.parse(request.responseText);
            } catch {
                response = null;
            }
            if (request.status < 200 || request.status >= 300 || !response?.ok) {
                setError(response?.error || 'The document submission failed. No partial success was reported; please check your files and retry.');
                progressWrap.hidden = true;
                submitButton.disabled = false;
                submitButton.textContent = 'Submit all selected documents';
                return;
            }
            successMessage.textContent = response.notice || `${response.documents_uploaded} documents were submitted for review.`;
            successMessage.hidden = false;
            progressBar.value = 100;
            progressLabel.textContent = 'All selected documents were received.';
            submitButton.textContent = 'Submission received';
            window.setTimeout(() => window.location.reload(), 1300);
        });
        request.addEventListener('error', () => {
            setError('The upload connection failed. Check your connection and retry the complete submission.');
            progressWrap.hidden = true;
            submitButton.disabled = false;
            submitButton.textContent = 'Submit all selected documents';
        });
        request.send(body);
    });
    renderPreview();
}
const roleSelect = document.getElementById('account-role');
const companyField = document.getElementById('company-field');
if (roleSelect && companyField) {
    const companyInput = companyField.querySelector('input');
    const updateCompanyField = () => {
        const visible = roleSelect.value === 'employer';
        companyField.hidden = !visible;
        companyInput.required = visible;
    };
    roleSelect.addEventListener('change', updateCompanyField);
    updateCompanyField();
}
document.querySelectorAll('.inline-review').forEach((form) => {
    const status = form.querySelector('select[name="status"]');
    const interviewTime = form.querySelector('input[name="interview_at"]');
    const updateInterviewRequirement = () => {
        interviewTime.required = status.value === 'interview_scheduled';
    };
    status.addEventListener('change', updateInterviewRequirement);
    updateInterviewRequirement();
});

document.querySelectorAll('.bookmark-button').forEach((button) => {
    const bookmarkId = button.dataset.bookmarkId;
    const storageKey = bookmarkId ? 'smartjob_saved_opportunity_' + bookmarkId : null;
    if (!storageKey) {
        return;
    }
    const syncBookmarkState = () => {
        const saved = localStorage.getItem(storageKey) === '1';
        const defaultLabel = button.dataset.defaultLabel || 'Save';
        button.classList.toggle('is-saved', saved);
        button.setAttribute('aria-pressed', String(saved));
        button.textContent = saved ? 'Saved' : defaultLabel;
    };
    syncBookmarkState();
    button.addEventListener('click', () => {
        const saved = localStorage.getItem(storageKey) === '1';
        localStorage.setItem(storageKey, saved ? '0' : '1');
        syncBookmarkState();
    });
});
</script>
<?php if ($user): ?>
<script>
(() => {
    const csrf = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const setBadge = (root, count) => {
        const badge = root.querySelector('.activity-count');
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = count < 1;
    };
    const addEmpty = (container, text) => {
        const empty = document.createElement('p');
        empty.className = 'muted';
        empty.textContent = text;
        container.replaceChildren(empty);
    };
    const notificationRoot = document.querySelector('[data-activity="notifications"]');
    const messageRoot = document.querySelector('[data-activity="messages"]');
    const refreshNotifications = async () => {
        const response = await fetch('index.php?page=notification_feed', {headers: {'Accept': 'application/json'}});
        if (!response.ok) throw new Error('Unable to load notifications.');
        const data = await response.json();
        setBadge(notificationRoot, Number(data.unread_count));
        const container = notificationRoot.querySelector('[data-activity-items]');
        const markAll = notificationRoot.querySelector('[data-mark-all]');
        container.replaceChildren();
        markAll.hidden = Number(data.unread_count) < 1;
        if (!data.notifications.length) return addEmpty(container, 'No notifications yet.');
        for (const notification of data.notifications) {
            const link = document.createElement('a');
            link.className = `activity-item${notification.read_at ? '' : ' unread'}`;
            link.href = notification.url;
            link.dataset.notificationId = notification.id;
            link.dataset.unread = notification.read_at ? '0' : '1';
            const title = document.createElement('strong');
            title.textContent = notification.title;
            const message = document.createElement('span');
            message.textContent = notification.message;
            link.append(title, message);
            container.append(link);
        }
    };
    const refreshMessages = async () => {
        if (!messageRoot) return;
        const response = await fetch('index.php?page=message_summary', {headers: {'Accept': 'application/json'}});
        if (!response.ok) throw new Error('Unable to load message summary.');
        const data = await response.json();
        setBadge(messageRoot, Number(data.unread_count));
        const container = messageRoot.querySelector('[data-activity-items]');
        container.replaceChildren();
        if (!data.threads.length) return addEmpty(container, 'No conversations yet.');
        for (const thread of data.threads) {
            const link = document.createElement('a');
            link.className = `activity-item${Number(thread.unread) ? ' unread' : ''}`;
            link.href = `index.php?page=messages&conversation_id=${encodeURIComponent(thread.id)}`;
            const title = document.createElement('strong');
            title.textContent = thread.subject;
            const preview = document.createElement('span');
            preview.textContent = `${thread.other_name || 'Participant'} · ${thread.last_message || 'No messages yet'}`;
            link.append(title, preview);
            container.append(link);
        }
    };
    const refreshActivity = () => {
        refreshNotifications().catch((error) => console.error(error));
        refreshMessages().catch((error) => console.error(error));
    };
    const markRead = async (id) => {
        const body = new URLSearchParams({_csrf: csrf, action: 'mark_notification_read', notification_id: id});
        const response = await fetch('index.php', {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded'}, body});
        if (!response.ok) throw new Error('Unable to mark the notification as read.');
    };
    document.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-notification-id][data-unread="1"]');
        if (!link) return;
        event.preventDefault();
        try {
            await markRead(link.dataset.notificationId);
            window.location.assign(link.href);
        } catch (error) {
            window.alert('This notification could not be marked as read. Please try again.');
            console.error(error);
        }
    });
    const markAll = notificationRoot.querySelector('[data-mark-all]');
    markAll.addEventListener('click', async () => {
        const body = new URLSearchParams({_csrf: csrf, action: 'mark_all_notifications_read'});
        const response = await fetch('index.php', {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded'}, body});
        if (!response.ok) {
            window.alert('Notifications could not be marked as read. Please try again.');
            return;
        }
        refreshNotifications().catch((error) => console.error(error));
    });
    const replyForm = document.querySelector('.reply-form');
    if (replyForm) {
        replyForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = replyForm.querySelector('button');
            button.disabled = true;
            try {
                const response = await fetch('index.php', {method: 'POST', headers: {'Accept': 'application/json'}, body: new FormData(replyForm)});
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'Message could not be sent.');
                replyForm.querySelector('textarea').value = '';
                if (window.smartjobPollMessages) await window.smartjobPollMessages();
                await refreshMessages();
                replyForm.querySelector('textarea').focus();
            } catch (error) {
                window.alert(error.message);
            } finally {
                button.disabled = false;
            }
        });
    }
    refreshActivity();
    window.setInterval(refreshActivity, 30000);
})();
</script>
<?php endif; ?>
<?php if ($user && in_array($user['role'], ['student', 'employer'], true)): ?><script src="assets/js/ai-writing.js?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/assets/js/ai-writing.js') ?>" defer></script><?php endif; ?>
<?php if (in_array($page, ['verify_email', 'verify_phone'], true)): ?><script src="assets/js/verification.js" defer></script><?php endif; ?>
<?php if ($page === 'biodata'): ?><script src="assets/js/education.js?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/assets/js/education.js') ?>" defer></script><?php endif; ?>
</body>
</html>
