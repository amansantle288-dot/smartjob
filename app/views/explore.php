<section class="page-heading"><p class="eyebrow">DISCOVER YOUR NEXT STEP</p><h1>Explore internships</h1><p class="muted">Browse approved companies and internship opportunities with clear application deadlines.</p></section>

<?php
$exploreCredentials = [];
if ($user && $user['role'] === 'student') {
    $credentialsQuery = db()->prepare('SELECT id, category, original_name FROM tbl_student_credentials WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
    $credentialsQuery->execute([(int) $user['id']]);
    $exploreCredentials = $credentialsQuery->fetchAll();
}
?>

<?php if (!empty($suggestions)): ?>
    <section class="suggestion-section"><div class="section-heading"><div><p class="eyebrow">PICKED FOR YOUR PROFILE</p><h2>Suggested companies</h2><p class="muted">Based on your skills, course, and preferred location.</p></div></div><div class="company-grid"><?php foreach ($suggestions as $suggestion): ?><a class="company-card" href="index.php?page=explore&amp;company_id=<?= (int) $suggestion['id'] ?>"><span class="company-mark" aria-hidden="true"><?php if (!empty($suggestion['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $suggestion['id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($suggestion['name'], 0, 1))) ?><?php endif; ?></span><span class="company-card-copy"><strong><?= h($suggestion['name']) ?></strong><small><?= h($suggestion['industry'] ?: 'Verified company') ?><?= $suggestion['address'] ? ' · ' . h($suggestion['address']) : '' ?></small></span><span class="match-label">Suggested</span></a><?php endforeach; ?></div></section>
<?php endif; ?>

<form class="card explore-filters" method="get" action="index.php">
    <input type="hidden" name="page" value="explore">
    <label>Company name<input name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Search companies"></label>
    <label>Industry<select name="industry"><option value="">All industries</option><?php foreach ($industries as $industry): ?><option value="<?= h($industry) ?>" <?= (string) ($_GET['industry'] ?? '') === $industry ? 'selected' : '' ?>><?= h($industry) ?></option><?php endforeach; ?></select></label>
    <label>Location<input name="location" value="<?= h($_GET['location'] ?? '') ?>" placeholder="City or province"></label>
    <label>Internship category<select name="category"><option value="">All categories</option><?php foreach ($categories as $option): ?><option value="<?= h($option) ?>" <?= (string) ($_GET['category'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
    <label>Required skill<input name="skill" maxlength="120" value="<?= h($_GET['skill'] ?? '') ?>" placeholder="e.g. PHP, design"></label>
    <div class="filter-actions"><button class="button" type="submit">Search</button><a class="button button-quiet" href="index.php?page=explore">Clear filters</a></div>
</form>

<?php if (empty($opportunities)): ?>
    <div class="empty-state">No internship opportunities match these filters. Try a different company, location, industry, or skill.</div>
    <?php if ($selectedCompany): ?>
        <section class="card saved-company-profile"><div><p class="eyebrow">VERIFIED COMPANY</p><h2><?= h($selectedCompany['name']) ?></h2><p class="muted"><?= h($selectedCompany['industry'] ?: 'Industry not listed') ?><?= $selectedCompany['address'] ? ' · ' . h($selectedCompany['address']) : '' ?></p><?php if ($selectedCompany['description']): ?><p><?= nl2br(h($selectedCompany['description'])) ?></p><?php endif; ?><?php if ($selectedCompany['website'] && filter_var($selectedCompany['website'], FILTER_VALIDATE_URL)): ?><a class="text-link" href="<?= h($selectedCompany['website']) ?>" target="_blank" rel="noopener noreferrer">Visit company website ↗</a><?php endif; ?></div><?php if ($user && $user['role'] === 'student'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= !empty($selectedCompany['is_saved']) ? 'unsave_company' : 'save_company' ?>"><input type="hidden" name="company_id" value="<?= (int) $selectedCompany['id'] ?>"><input type="hidden" name="return_page" value="explore"><button class="button<?= !empty($selectedCompany['is_saved']) ? ' button-quiet' : '' ?>" type="submit"><?= !empty($selectedCompany['is_saved']) ? 'Saved' : 'Save company' ?></button></form><?php endif; ?></section>
    <?php endif; ?>
<?php else: ?>
    <div class="explore-shell">
        <aside class="job-list" aria-label="Available internship opportunities">
            <div class="job-list-header">
                <div>
                    <p class="eyebrow">INTERNSHIP OPPORTUNITIES</p>
                    <h2><?= count($opportunities) ?> opportunity<?= count($opportunities) === 1 ? '' : 'ies' ?></h2>
                </div>
                <?php if ($selectedCompany && $selectedCompany['name']): ?><a class="text-link" href="index.php?page=explore&amp;company_id=<?= (int) $selectedCompany['id'] ?>">Company profile</a><?php endif; ?>
            </div>
            <?php foreach ($opportunities as $opportunity): ?>
                <?php $selected = $selectedOpportunity && (int) $selectedOpportunity['id'] === (int) $opportunity['id']; ?>
                <article class="job-card<?= $selected ? ' is-selected' : '' ?>">
                    <div class="job-card-head">
                        <div class="job-company-meta">
                            <span class="company-mark company-mark-small" aria-hidden="true"><?php if (!empty($opportunity['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $opportunity['company_id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($opportunity['company_name'], 0, 1))) ?><?php endif; ?></span>
                            <div>
                                <strong><?= h($opportunity['company_name']) ?></strong>
                                <small><?= h($opportunity['industry'] ?: 'Verified company') ?></small>
                            </div>
                        </div>
                        <?php if ($user && $user['role'] === 'student'): ?>
                            <form method="post" class="bookmark-form"><?= csrf_field() ?><input type="hidden" name="action" value="<?= !empty($opportunity['is_saved']) ? 'unsave_internship' : 'save_internship' ?>"><input type="hidden" name="internship_id" value="<?= (int) $opportunity['id'] ?>"><input type="hidden" name="return_page" value="explore"><button class="bookmark-button<?= !empty($opportunity['is_saved']) ? ' is-saved' : '' ?>" type="submit" aria-pressed="<?= !empty($opportunity['is_saved']) ? 'true' : 'false' ?>"><?= !empty($opportunity['is_saved']) ? 'Saved' : 'Save' ?></button></form>
                        <?php else: ?>
                            <button class="bookmark-button" type="button" data-bookmark-id="<?= (int) $opportunity['id'] ?>" data-default-label="Save" aria-pressed="false">Save</button>
                        <?php endif; ?>
                    </div>
                    <a class="job-card-main" href="index.php?page=explore&amp;internship_id=<?= (int) $opportunity['id'] ?><?= !empty($_GET['company_id']) ? '&amp;company_id=' . (int) $_GET['company_id'] : '' ?><?= !empty($_GET['q']) ? '&amp;q=' . rawurlencode((string) $_GET['q']) : '' ?><?= !empty($_GET['industry']) ? '&amp;industry=' . rawurlencode((string) $_GET['industry']) : '' ?><?= !empty($_GET['location']) ? '&amp;location=' . rawurlencode((string) $_GET['location']) : '' ?><?= !empty($_GET['category']) ? '&amp;category=' . rawurlencode((string) $_GET['category']) : '' ?><?= !empty($_GET['skill']) ? '&amp;skill=' . rawurlencode((string) $_GET['skill']) : '' ?>">
                        <h3><?= h($opportunity['title']) ?></h3>
                        <p><?= h($opportunity['location'] ?: 'Location not listed') ?> · <?= h(ucfirst($opportunity['work_mode'])) ?></p>
                    </a>
                    <div class="job-chip-row">
                        <span class="chip"><?= h($opportunity['category'] ?: 'Internship') ?></span>
                        <span class="chip subtle"><?= (int) $opportunity['slots'] ?> slot<?= (int) $opportunity['slots'] === 1 ? '' : 's' ?></span>
                        <span class="chip subtle"><?= h(ucfirst($opportunity['work_mode'])) ?></span>
                    </div>
                    <div class="job-card-footer">
                        <span>Posted <?= h($opportunity['posted_date']) ?> · <?= (int) $opportunity['hiring_duration_days'] ?> days · Deadline <?= h(date('M j, Y', strtotime((string) $opportunity['application_deadline']))) ?></span>
                        <div class="job-card-meta-right">
                            <span class="badge <?= h($opportunity['hiring_status']['badge_class']) ?>"><?= h($opportunity['hiring_status']['label']) ?><?= $opportunity['hiring_status']['state'] === 'closed' ? '' : ' · ' . (int) $opportunity['hiring_status']['days_remaining'] . ' days left' ?></span>
                            <?php if (isset($opportunity['application_status'])): ?>
                                <span class="status-badge subtle status-<?= h(strtolower((string) $opportunity['application_status'])) ?>"><?= h(ucwords(str_replace('_', ' ', (string) $opportunity['application_status']))) ?></span>
                            <?php elseif (isset($opportunity['match_score'])): ?>
                                <strong><?= (int) $opportunity['match_score'] ?>% match</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </aside>

        <aside class="job-detail-panel" aria-live="polite">
            <?php if ($selectedOpportunity): ?>
                <div class="job-detail-inner">
                    <div class="job-detail-header">
                        <div class="detail-topline">
                            <span class="badge published"><?= h($selectedOpportunity['category'] ?: 'Internship') ?></span>
                            <?php if (isset($selectedOpportunity['match_score'])): ?><span class="match-pill"><?= (int) $selectedOpportunity['match_score'] ?>% match</span><?php endif; ?>
                        </div>
                        <?php if ($user && $user['role'] === 'student'): ?>
                            <form method="post" class="bookmark-form"><?= csrf_field() ?><input type="hidden" name="action" value="<?= !empty($selectedOpportunity['is_saved']) ? 'unsave_internship' : 'save_internship' ?>"><input type="hidden" name="internship_id" value="<?= (int) $selectedOpportunity['id'] ?>"><input type="hidden" name="return_page" value="explore"><button class="bookmark-button detail-bookmark<?= !empty($selectedOpportunity['is_saved']) ? ' is-saved' : '' ?>" type="submit" aria-pressed="<?= !empty($selectedOpportunity['is_saved']) ? 'true' : 'false' ?>"><?= !empty($selectedOpportunity['is_saved']) ? 'Saved' : 'Save vacancy' ?></button></form>
                        <?php else: ?>
                            <button class="bookmark-button detail-bookmark" type="button" data-bookmark-id="<?= (int) $selectedOpportunity['id'] ?>" data-default-label="Save vacancy" aria-pressed="false">Save vacancy</button>
                        <?php endif; ?>
                    </div>

                    <div class="company-profile-heading job-company-block">
                        <span class="company-mark company-mark-large" aria-hidden="true"><?php if (!empty($selectedOpportunity['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $selectedOpportunity['company_id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($selectedOpportunity['company_name'], 0, 1))) ?><?php endif; ?></span>
                        <div>
                            <p class="eyebrow"><?= h($selectedOpportunity['industry'] ?: 'APPROVED COMPANY') ?></p>
                            <h2><?= h($selectedOpportunity['company_name']) ?></h2>
                            <p class="muted"><?= h($selectedOpportunity['address'] ?: 'Location not listed') ?></p>
                        </div>
                    </div>

                    <h3 class="role-title"><?= h($selectedOpportunity['title']) ?></h3>
                    <p class="detail-summary"><?= h($selectedOpportunity['location'] ?: 'Location not listed') ?> · <?= h(ucfirst($selectedOpportunity['work_mode'])) ?> · <?= (int) $selectedOpportunity['slots'] ?> slot<?= (int) $selectedOpportunity['slots'] === 1 ? '' : 's' ?></p>

                    <div class="detail-metrics">
                        <div><span>Date posted</span><strong><?= h($selectedOpportunity['posted_date']) ?></strong></div>
                        <div><span>Hiring duration</span><strong><?= (int) $selectedOpportunity['hiring_duration_days'] ?> days</strong></div>
                        <div><span>Deadline</span><strong><?= h(date('M j, Y', strtotime((string) $selectedOpportunity['application_deadline']))) ?></strong></div>
                        <div><span>Hiring status</span><strong><?= h($selectedOpportunity['hiring_status']['label']) ?><?= $selectedOpportunity['hiring_status']['state'] === 'closed' ? '' : ' · ' . (int) $selectedOpportunity['hiring_status']['days_remaining'] . ' days' ?></strong></div>
                    </div>

                    <div class="detail-section">
                        <h4>About the internship</h4>
                        <p><?= nl2br(h($selectedOpportunity['description'])) ?></p>
                    </div>

                    <?php if (!empty($selectedOpportunity['requirements'])): ?>
                        <div class="detail-section">
                            <h4>Responsibilities & requirements</h4>
                            <p><?= nl2br(h($selectedOpportunity['requirements'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($selectedOpportunity['required_skills_list'])): ?>
                        <div class="detail-section">
                            <h4>Required skills</h4>
                            <div class="skill-tags"><?php foreach ($selectedOpportunity['required_skills_list'] as $skill): ?><span class="skill-tag"><?= h($skill) ?></span><?php endforeach; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($user && $user['role'] === 'student'): ?>
                        <div class="detail-section match-section">
                            <h4>SMARTMATCH compatibility</h4>
                            <div class="match-score-banner">
                                <strong><?= (int) ($selectedOpportunity['match_score'] ?? 0) ?>%</strong>
                                <span><?= !empty($selectedOpportunity['missing_skills']) ? 'Missing: ' . h(implode(', ', array_map('strval', $selectedOpportunity['missing_skills']))) : 'Strong fit for your profile' ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($selectedCompany && ($selectedCompany['description'] || $selectedCompany['mission_statement'] || $selectedCompany['vision_statement'] || $selectedCompany['website'])): ?>
                        <div class="detail-section">
                            <h4>Company profile</h4>
                            <?php if ($selectedCompany['description']): ?><p><?= nl2br(h($selectedCompany['description'])) ?></p><?php endif; ?>
                            <?php if (!empty($selectedCompany['mission_statement'])): ?><p><strong>Mission:</strong> <?= nl2br(h($selectedCompany['mission_statement'])) ?></p><?php endif; ?>
                            <?php if (!empty($selectedCompany['vision_statement'])): ?><p><strong>Vision:</strong> <?= nl2br(h($selectedCompany['vision_statement'])) ?></p><?php endif; ?>
                            <?php if ($selectedCompany['website'] && filter_var($selectedCompany['website'], FILTER_VALIDATE_URL)): ?><a class="text-link" href="<?= h($selectedCompany['website']) ?>" target="_blank" rel="noopener noreferrer">Visit company website ↗</a><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="detail-actions">
                        <?php if ($user && $user['role'] === 'student'): ?>
                            <?php if (!empty($selectedOpportunity['application_status'])): ?>
                                <span class="badge <?= h($selectedOpportunity['application_status']) ?>">Applied · <?= h(ucwords(str_replace('_', ' ', $selectedOpportunity['application_status']))) ?></span>
                                <a class="button button-quiet" href="index.php?page=applications">View application</a>
                            <?php elseif ($selectedOpportunity['hiring_status']['can_apply']): ?>
                            <form method="post" class="apply-form" data-ai-context='<?= h(json_encode(['internship_title' => $selectedOpportunity['title'], 'company_name' => $selectedOpportunity['company_name'], 'opportunity_description' => $selectedOpportunity['description']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) ?>'><?= csrf_field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="internship_id" value="<?= (int) $selectedOpportunity['id'] ?>">
                                <label>Application letter or internship objectives <span class="field-help">Optional · visible to the employer</span><textarea name="cover_letter" data-ai-writing="student_application_letter" data-ai-writing-options="student_application_letter,student_internship_objectives" maxlength="5000" rows="4" placeholder="Introduce your interest and explain what you hope to contribute or learn."></textarea></label>
                                <details class="share-documents"><summary>Optional documents</summary>
                                    <label class="checkbox-label"><input type="checkbox" name="share_biodata" value="1"> Share my e-Biodata</label>
                                    <?php foreach ($exploreCredentials as $credential): ?><label class="checkbox-label"><input type="checkbox" name="credential_ids[]" value="<?= (int) $credential['id'] ?>"> <?= h(ucwords(str_replace('_', ' ', $credential['category']))) ?> · <?= h($credential['original_name']) ?></label><?php endforeach; ?>
                                    <?php if (!$exploreCredentials): ?><p class="muted">No supporting documents uploaded yet.</p><?php endif; ?>
                                </details>
                                <button class="button" type="submit">Apply now</button>
                            </form>
                            <?php else: ?>
                                <p class="notice"><?= h($selectedOpportunity['hiring_status']['label']) ?>. This opportunity is no longer accepting applications.</p>
                            <?php endif; ?>
                        <?php elseif (!$user && $selectedOpportunity['hiring_status']['can_apply']): ?>
                            <a class="button" href="index.php?page=register">Create account to apply</a>
                            <a class="button button-quiet" href="index.php?page=login">Log in</a>
                        <?php endif; ?>
                        <a class="button button-quiet" href="index.php?page=explore&amp;company_id=<?= (int) $selectedOpportunity['company_id'] ?>">View company profile</a>
                        <?php if ($user && $user['role'] === 'student' && $selectedCompany): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= !empty($selectedCompany['is_saved']) ? 'unsave_company' : 'save_company' ?>"><input type="hidden" name="company_id" value="<?= (int) $selectedCompany['id'] ?>"><input type="hidden" name="return_page" value="explore"><button class="button button-quiet" type="submit"><?= !empty($selectedCompany['is_saved']) ? 'Company saved' : 'Save company' ?></button></form><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </div>
<?php endif; ?>

<?php if (!empty($companies)): ?>
    <div class="section-heading"><div><p class="eyebrow">APPROVED EMPLOYERS</p><h2>Company directory</h2><p class="muted"><?= count($companies) ?> approved compan<?= count($companies) === 1 ? 'y' : 'ies' ?> with internship listings.</p></div></div>
    <div class="company-grid"><?php foreach ($companies as $company): ?><a class="company-card" href="index.php?page=explore&amp;company_id=<?= (int) $company['id'] ?>"><span class="company-mark" aria-hidden="true"><?php if (!empty($company['logo_key'])): ?><img src="index.php?page=company_logo&amp;id=<?= (int) $company['id'] ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($company['name'], 0, 1))) ?><?php endif; ?></span><span class="company-card-copy"><strong><?= h($company['name']) ?></strong><small><?= h($company['industry'] ?: 'Verified company') ?><?= $company['address'] ? ' · ' . h($company['address']) : '' ?></small></span><span class="company-arrow" aria-hidden="true">→</span></a><?php endforeach; ?></div>
<?php endif; ?>
