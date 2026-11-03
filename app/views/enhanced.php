<?php
$studentId = (int) $user['id'];
?>
<?php if ($page === 'biodata'): ?>
    <?php
    $data = load_biodata($studentId);
    $visible = $data['visibility'];
    ?>
    <div class="sj-student-profile-container">
    <section class="page-heading"><p class="eyebrow">YOUR PROFESSIONAL PROFILE</p><h1>My e-Biodata</h1><p class="muted">Maintain your details and decide which optional sections appear in your preview and export. This page is private to you.</p></section>
    <div class="feature-actions"><a class="button button-small" href="index.php?page=biodata_pdf">Download PDF</a><button class="button button-small button-quiet" type="button" onclick="window.print()">Print biodata</button><a class="button button-small button-quiet" href="#biodata-preview">Preview below</a></div>
    <div class="sj-profile-sections">
    <form class="card form-card enhanced-form sj-profile-section" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_biodata">
        <h2>Personal Information</h2>
        <p class="muted">Name, email, and mobile number come from your SMARTJOB account. Update contact details in <a class="text-link" href="index.php?page=account_settings">Account settings</a>.</p>
        <div class="form-grid">
            <label>Full name<input value="<?= h($data['full_name']) ?>" disabled></label>
            <label>Email<input value="<?= h($data['email']) ?>" disabled></label>
            <label>Registered mobile number<input value="<?= h($data['phone'] ?? '') ?>" disabled></label>
            <label>Date of birth <span class="field-help">optional</span><input type="date" name="date_of_birth" value="<?= h($data['date_of_birth'] ?? '') ?>"></label>
            <label>City / municipality<input name="city" maxlength="120" value="<?= h($data['city'] ?? '') ?>"></label>
            <label>Province<input name="province" maxlength="120" value="<?= h($data['province'] ?? '') ?>"></label>
        </div>
        <label>Current address<input name="address" maxlength="255" value="<?= h($data['address'] ?? '') ?>"></label>
        <h3>Professional Summary</h3>
        <label>Professional summary<textarea name="summary" data-ai-writing="student_summary" maxlength="3000" rows="4"><?= h($data['summary'] ?? '') ?></textarea></label>
        <h3>Additional e-Biodata Information</h3>
        <label>Layout<select name="template"><option value="standard" <?= ($data['template'] ?? 'standard') === 'standard' ? 'selected' : '' ?>>Standard Internship Biodata</option><option value="modern" <?= ($data['template'] ?? '') === 'modern' ? 'selected' : '' ?>>Modern Professional Biodata</option></select></label>
        <h3>Include in biodata export</h3>
        <div class="checks"><?php foreach (['education' => 'Education', 'experience' => 'Experience', 'projects' => 'Projects', 'skills' => 'Skills', 'training' => 'Training', 'certifications' => 'Certifications', 'date_of_birth' => 'Date of birth'] as $key => $label): ?><label class="checkbox-label"><input type="checkbox" name="visibility[]" value="<?= h($key) ?>" <?= in_array($key, $visible, true) ? 'checked' : '' ?>> <?= h($label) ?></label><?php endforeach; ?></div>
        <button class="button" type="submit">Save e-Biodata</button>
    </form>

    <?php
    $educationBackground = $data['education_background'];
    $tertiaryComplete = false;
    foreach ($data['education'] as $record) {
        if (trim((string) ($record['school_name'] ?? '')) !== '' && trim((string) ($record['program'] ?? '')) !== '') {
            $tertiaryComplete = true;
            break;
        }
    }
    $educationSections = [
        'Primary Education' => !empty($educationBackground['elementary_school']),
        'Junior High School' => !empty($educationBackground['junior_high_school']),
        'Senior High School' => !empty($educationBackground['senior_high_school']) || !empty($educationBackground['senior_high_not_applicable']),
        'Tertiary Education' => $tertiaryComplete,
    ];
    $educationSectionsComplete = count(array_filter($educationSections));
    $educationProgress = (int) round($educationSectionsComplete / count($educationSections) * 100);
    $educationMissingSections = array_keys(array_filter($educationSections, static fn (bool $complete): bool => !$complete));
    $educationProgressMessage = $educationMissingSections
        ? 'Still to document: ' . implode(', ', $educationMissingSections) . '. Optional school stages may be left blank; mark senior high school not applicable when appropriate.'
        : 'All applicable school stages and tertiary details are documented.';
    $yearMax = (int) date('Y');
    $yearFutureMax = $yearMax + 15;
    $yearValue = static fn (array $source, string $key): string => !empty($source[$key]) ? (string) $source[$key] : '';
    $levelComplete = static fn (array $source, string $key): bool => trim((string) ($source[$key] ?? '')) !== '';
    ?>
    <section id="education-background" class="education-module sj-profile-section">
        <header class="education-page-heading">
            <div class="education-page-heading-copy">
                <p class="eyebrow"><i class="bi bi-mortarboard" aria-hidden="true"></i> YOUR ACADEMIC HISTORY</p>
                <h1>Educational Background</h1>
                <p class="muted">Manage your academic history and qualifications for internship applications.</p>
            </div>
            <a class="button button-small button-quiet" href="#biodata-preview"><i class="bi bi-file-earmark-person" aria-hidden="true"></i> Preview e-Biodata</a>
        </header>
        <section class="card education-progress-card" aria-label="Educational profile completion">
            <div class="education-progress-copy">
                <strong>Educational Profile Completion</strong>
                <span data-education-progress-text><span data-education-completed-count><?= $educationSectionsComplete ?></span> of <?= count($educationSections) ?> school stages documented. This is informational; optional education fields can be left blank.</span>
                <small data-education-progress-message><?= h($educationProgressMessage) ?></small>
            </div>
            <div class="education-progress-meter" data-education-progressbar role="progressbar" aria-label="Educational profile completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $educationProgress ?>"><span style="width:<?= $educationProgress ?>%"></span></div>
            <strong class="education-progress-value" data-education-progress-value><?= $educationProgress ?>%</strong>
        </section>
        <div class="education-level-list">
            <details id="education-primary" class="card education-level-card" data-education-card data-completion-level="primary">
                <summary>
                    <span class="education-level-icon"><i class="bi bi-book" aria-hidden="true"></i></span>
                    <span class="education-level-heading"><strong>Primary Education</strong><small>Elementary school information</small></span>
                    <span class="education-level-status <?= $levelComplete($educationBackground, 'elementary_school') ? 'is-complete' : 'is-optional' ?>" data-education-level-status="primary"><?= $levelComplete($educationBackground, 'elementary_school') ? '<i class="bi bi-check-circle" aria-hidden="true"></i> Complete' : 'Optional' ?></span>
                    <i class="bi bi-chevron-down education-chevron" aria-hidden="true"></i>
                </summary>
                <form method="post" class="education-level-form education-ajax-form" data-completion-field="elementary_school"><?= csrf_field() ?><input type="hidden" name="action" value="save_education_background">
                    <div class="form-grid">
                        <label>School name<input name="elementary_school" maxlength="190" value="<?= h($educationBackground['elementary_school'] ?? '') ?>"></label>
                        <label>School address<input name="elementary_location" maxlength="255" value="<?= h($educationBackground['elementary_location'] ?? '') ?>"></label>
                        <label>Year started<input name="elementary_year_started" type="number" min="1900" max="<?= $yearMax ?>" value="<?= h($yearValue($educationBackground, 'elementary_year_started')) ?>"></label>
                        <label>Year graduated<input name="elementary_year_graduated" type="number" min="1900" max="<?= $yearMax ?>" value="<?= h($yearValue($educationBackground, 'elementary_year_graduated')) ?>"></label>
                        <label class="education-field-wide">Honors or achievements <span class="field-help">Optional</span><textarea name="elementary_honors" data-ai-writing="student_achievement" maxlength="2000" rows="2"><?= h($educationBackground['elementary_honors'] ?? '') ?></textarea></label>
                    </div>
                    <button class="button button-small" type="submit"><i class="bi bi-check-circle" aria-hidden="true"></i> Save primary education</button><span class="education-form-status" aria-live="polite"></span>
                </form>
            </details>
            <details id="education-junior-high" class="card education-level-card" data-education-card data-completion-level="junior_high">
                <summary>
                    <span class="education-level-icon"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></span>
                    <span class="education-level-heading"><strong>Junior High School</strong><small>Junior high school information</small></span>
                    <span class="education-level-status <?= $levelComplete($educationBackground, 'junior_high_school') ? 'is-complete' : 'is-optional' ?>" data-education-level-status="junior_high"><?= $levelComplete($educationBackground, 'junior_high_school') ? '<i class="bi bi-check-circle" aria-hidden="true"></i> Complete' : 'Optional' ?></span>
                    <i class="bi bi-chevron-down education-chevron" aria-hidden="true"></i>
                </summary>
                <form method="post" class="education-level-form education-ajax-form" data-completion-field="junior_high_school"><?= csrf_field() ?><input type="hidden" name="action" value="save_education_background">
                    <div class="form-grid">
                        <label>School name<input name="junior_high_school" maxlength="190" value="<?= h($educationBackground['junior_high_school'] ?? '') ?>"></label>
                        <label>School address<input name="junior_high_location" maxlength="255" value="<?= h($educationBackground['junior_high_location'] ?? '') ?>"></label>
                        <label>Year started<input name="junior_high_year_started" type="number" min="1900" max="<?= $yearMax ?>" value="<?= h($yearValue($educationBackground, 'junior_high_year_started')) ?>"></label>
                        <label>Year completed<input name="junior_high_year_completed" type="number" min="1900" max="<?= $yearMax ?>" value="<?= h($yearValue($educationBackground, 'junior_high_year_completed')) ?>"></label>
                        <label class="education-field-wide">Honors or achievements <span class="field-help">Optional</span><textarea name="junior_high_honors" data-ai-writing="student_achievement" maxlength="2000" rows="2"><?= h($educationBackground['junior_high_honors'] ?? '') ?></textarea></label>
                    </div>
                    <button class="button button-small" type="submit"><i class="bi bi-check-circle" aria-hidden="true"></i> Save junior high school</button><span class="education-form-status" aria-live="polite"></span>
                </form>
            </details>
            <details id="education-senior-high" class="card education-level-card" data-education-card data-completion-level="senior_high">
                <summary>
                    <span class="education-level-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
                    <span class="education-level-heading"><strong>Senior High School</strong><small>Senior high school information</small></span>
                    <?php $seniorHighStatus = !empty($educationBackground['senior_high_not_applicable']) ? 'Not applicable' : ($levelComplete($educationBackground, 'senior_high_school') ? '<i class="bi bi-check-circle" aria-hidden="true"></i> Complete' : 'Optional'); ?>
                    <span class="education-level-status <?= !empty($educationBackground['senior_high_not_applicable']) || $levelComplete($educationBackground, 'senior_high_school') ? 'is-complete' : 'is-optional' ?>" data-education-level-status="senior_high"><?= $seniorHighStatus ?></span>
                    <i class="bi bi-chevron-down education-chevron" aria-hidden="true"></i>
                </summary>
                <form method="post" class="education-level-form education-ajax-form" data-completion-field="senior_high_school"><?= csrf_field() ?><input type="hidden" name="action" value="save_education_background"><input type="hidden" name="senior_high_not_applicable" value="0">
                    <div class="form-grid">
                        <label class="checkbox-label education-field-wide"><input type="checkbox" name="senior_high_not_applicable" value="1" <?= !empty($educationBackground['senior_high_not_applicable']) ? 'checked' : '' ?>> Not applicable to my educational pathway</label>
                        <label>School name<input name="senior_high_school" maxlength="190" value="<?= h($educationBackground['senior_high_school'] ?? '') ?>"></label>
                        <label>School address<input name="senior_high_location" maxlength="255" value="<?= h($educationBackground['senior_high_location'] ?? '') ?>"></label>
                        <label>Strand or track<input name="senior_high_strand" list="senior-high-strands" maxlength="120" value="<?= h($educationBackground['senior_high_strand'] ?? '') ?>" placeholder="Choose or enter a strand"><datalist id="senior-high-strands"><option value="STEM"><option value="ABM"><option value="HUMSS"><option value="GAS"><option value="TVL"><option value="Arts and Design"><option value="Sports"></datalist></label>
                        <label>Year started<input name="senior_high_year_started" type="number" min="1900" max="<?= $yearMax ?>" value="<?= h($yearValue($educationBackground, 'senior_high_year_started')) ?>"></label>
                        <label>Year graduated<input name="senior_high_year_graduated" type="number" min="1900" max="<?= $yearMax ?>" value="<?= h($yearValue($educationBackground, 'senior_high_year_graduated')) ?>"></label>
                        <label class="education-field-wide">Honors or achievements <span class="field-help">Optional</span><textarea name="senior_high_honors" data-ai-writing="student_achievement" maxlength="2000" rows="2"><?= h($educationBackground['senior_high_honors'] ?? '') ?></textarea></label>
                    </div>
                    <button class="button button-small" type="submit"><i class="bi bi-check-circle" aria-hidden="true"></i> Save senior high school</button><span class="education-form-status" aria-live="polite"></span>
                </form>
            </details>
        </div>
        <section class="card education-tertiary-section" id="education-tertiary">
            <header class="education-section-heading">
                <div class="education-section-title">
                    <span class="education-level-icon"><i class="bi bi-buildings" aria-hidden="true"></i></span>
                    <div><h2>Tertiary Education</h2><p class="muted">Manage your college or university education records.</p></div>
                </div>
                <div class="education-section-actions">
                    <span class="education-level-status <?= $tertiaryComplete ? 'is-complete' : 'is-optional' ?>" data-education-level-status="tertiary" data-is-complete="<?= $tertiaryComplete ? '1' : '0' ?>"><?= count($data['education']) ?> record<?= count($data['education']) === 1 ? '' : 's' ?></span>
                    <button class="button button-small" type="button" data-open-tertiary-form><i class="bi bi-plus-circle" aria-hidden="true"></i> Add Tertiary Record</button>
                </div>
            </header>
            <div class="education-tertiary-form-wrapper" data-tertiary-form-wrapper hidden>
                <form method="post" class="education-record-form education-tertiary-form" data-tertiary-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_education">
                    <input type="hidden" name="record_id" value="">
                    <input type="hidden" name="education_level" value="tertiary">
                    <input type="hidden" name="is_current" value="0">
                    <header class="education-tertiary-form-heading"><h3 data-tertiary-form-title>Add Tertiary Record</h3><p class="muted">Enter the institution and program details for this record.</p></header>
                    <div class="education-tertiary-form-grid">
                        <label>College or University<input name="school_name" maxlength="190" required></label>
                        <label>Degree or Program<input name="education_program" maxlength="160" required></label>
                        <label>Major or Specialization<input name="major" maxlength="160"></label>
                        <label>Department <span class="field-help">Optional</span><input name="department" maxlength="160"></label>
                        <label>Year Level <span class="field-help">Optional</span><input name="education_year" list="tertiary-year-levels" maxlength="40" placeholder="e.g. 3rd year"><datalist id="tertiary-year-levels"><option value="1st year"><option value="2nd year"><option value="3rd year"><option value="4th year"><option value="5th year"><option value="Graduate"></datalist></label>
                        <label>School Address or Location<input name="location" maxlength="255"></label>
                        <label>Year Started<input name="start_year" type="number" min="1900" max="<?= $yearMax ?>"></label>
                        <label>Academic Status<select name="academic_status" data-academic-status><?php foreach (['currently_enrolled' => 'Currently Enrolled', 'graduated' => 'Graduated', 'undergraduate' => 'Undergraduate', 'transferred' => 'Transferred', 'on_leave' => 'On Leave'] as $value => $label): ?><option value="<?= h($value) ?>"><?= h($label) ?></option><?php endforeach; ?></select></label>
                        <label data-expected-year>Expected Graduation Year<input name="expected_graduation_year" type="number" min="1900" max="<?= $yearFutureMax ?>"></label>
                        <label data-actual-year hidden>Actual Graduation Year<input name="actual_graduation_year" type="number" min="1900" max="<?= $yearMax ?>"></label>
                        <label class="checkbox-label education-field-wide"><input type="checkbox" name="is_current" value="1" checked> Use this as my current or primary tertiary program</label>
                        <label class="education-field-wide">Honors or Awards <span class="field-help">Optional</span><textarea name="achievements" data-ai-writing="student_achievement" maxlength="2000" rows="3"></textarea></label>
                    </div>
                    <div class="education-tertiary-form-actions">
                        <button class="button button-small button-quiet" type="button" data-cancel-tertiary-form>Cancel</button>
                        <button class="button button-small" type="submit" data-save-tertiary-record><i class="bi bi-check-circle" aria-hidden="true"></i> Save Tertiary Record</button>
                    </div>
                </form>
            </div>
            <h3 class="education-records-heading">Existing Tertiary Education Records</h3>
            <?php if ($data['education']): ?><ol class="education-history education-record-list"><?php foreach ($data['education'] as $item): ?>
                <?php
                $statusLabels = ['currently_enrolled' => 'Currently Enrolled', 'graduated' => 'Graduated', 'undergraduate' => 'Undergraduate', 'transferred' => 'Transferred', 'on_leave' => 'On Leave'];
                $itemStatus = ($item['academic_status'] ?? '') === 'undergraduate' && !empty($item['end_date']) ? 'graduated' : ($item['academic_status'] ?? 'undergraduate');
                $startYear = !empty($item['start_date']) ? substr((string) $item['start_date'], 0, 4) : '';
                $endYear = $itemStatus === 'graduated'
                    ? (!empty($item['end_date']) ? substr((string) $item['end_date'], 0, 4) : '')
                    : (!empty($item['expected_graduation_year']) ? (string) $item['expected_graduation_year'] : '');
                ?>
                <li class="education-history-item">
                    <span class="education-timeline-dot" aria-hidden="true"></span>
                    <article class="education-history-card education-tertiary-record">
                        <div class="education-history-summary">
                            <div>
                                <strong><?= h($item['school_name']) ?></strong>
                                <span><?= h($item['program']) ?><?= !empty($item['major']) ? ' · ' . h($item['major']) : '' ?></span>
                            </div>
                            <?php if (!empty($item['is_current'])): ?><span class="education-current-badge"><i class="bi bi-check-circle" aria-hidden="true"></i> Current program</span><?php endif; ?>
                        </div>
                        <dl class="education-record-details">
                            <div><dt>Years attended</dt><dd><?= h($startYear ?: 'Not provided') ?><?= $endYear ? ' – ' . h($endYear) : '' ?></dd></div>
                            <div><dt>Academic status</dt><dd><?= h($statusLabels[$itemStatus] ?? 'Education record') ?></dd></div>
                            <div><dt>Year level</dt><dd><?= h($item['year_level'] ?: 'Not provided') ?></dd></div>
                            <div><dt>Location</dt><dd><?= h($item['location'] ?: 'Not provided') ?></dd></div>
                        </dl>
                        <?php if (!empty($item['department'])): ?><p class="education-record-extra"><strong>Department:</strong> <?= h($item['department']) ?></p><?php endif; ?>
                        <?php if (!empty($item['achievements'])): ?><p class="education-record-extra"><strong>Honors or achievements:</strong> <?= h($item['achievements']) ?></p><?php endif; ?>
                    <button id="education-record-edit-<?= (int) $item['id'] ?>" class="button button-small button-quiet education-edit-trigger" type="button" data-edit-tertiary="<?= (int) $item['id'] ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i> Edit</button>
                    <details class="education-edit-details" hidden><summary>Edit record</summary><form method="post" class="education-record-form" data-record-id="<?= (int) $item['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="update_education"><input type="hidden" name="record_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="education_level" value="tertiary"><input type="hidden" name="is_current" value="0">
                    <div class="form-grid"><label>College or university<input name="school_name" maxlength="190" value="<?= h($item['school_name']) ?>" required></label><label>Degree or program<input name="education_program" maxlength="160" value="<?= h($item['program']) ?>" required></label><label>Major or specialization<input name="major" maxlength="160" value="<?= h($item['major'] ?? '') ?>"></label><label>Department<input name="department" maxlength="160" value="<?= h($item['department'] ?? '') ?>"></label><label>Year level<input name="education_year" maxlength="40" value="<?= h($item['year_level'] ?? '') ?>"></label><label>School address or location<input name="location" maxlength="255" value="<?= h($item['location'] ?? '') ?>"></label><label>Year started<input name="start_year" type="number" min="1900" max="<?= $yearMax ?>" value="<?= h($startYear) ?>"></label><label>Academic status<select name="academic_status" data-academic-status><?php foreach ($statusLabels as $value => $label): ?><option value="<?= h($value) ?>" <?= $itemStatus === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><label data-expected-year <?= $itemStatus === 'graduated' ? 'hidden' : '' ?>>Expected graduation year<input name="expected_graduation_year" type="number" min="1900" max="<?= $yearFutureMax ?>" value="<?= $itemStatus !== 'graduated' ? h($endYear) : '' ?>"></label><label data-actual-year <?= $itemStatus !== 'graduated' ? 'hidden' : '' ?>>Actual graduation year<input name="actual_graduation_year" type="number" min="1900" max="<?= $yearMax ?>" value="<?= $itemStatus === 'graduated' ? h($endYear) : '' ?>"></label><label class="checkbox-label education-field-wide"><input type="checkbox" name="is_current" value="1" <?= !empty($item['is_current']) ? 'checked' : '' ?>> Use this as my current or primary tertiary program</label><label class="education-field-wide">Honors or awards<textarea name="achievements" maxlength="2000" rows="2"><?= h($item['achievements'] ?? '') ?></textarea></label></div><button class="button button-small" type="submit">Save changes</button></form></details>
                    <form method="post" class="education-delete-form" onsubmit="return confirm('Remove this education record? This cannot be undone.');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_education"><input type="hidden" name="record_id" value="<?= (int) $item['id'] ?>"><button class="button button-small button-danger" type="submit" aria-label="Remove <?= h($item['school_name']) ?> education record"><i class="bi bi-trash" aria-hidden="true"></i> Remove</button></form>
                    </article>
                </li>
            <?php endforeach; ?></ol><?php else: ?><p class="muted education-empty">No tertiary records added yet. Existing degree details from your student profile remain available to matching until you add a record.</p><?php endif; ?>
        </section>
        <section class="card education-history-summary">
            <h2><i class="bi bi-clock-history" aria-hidden="true"></i> Educational History</h2>
            <p class="muted education-history-intro">A clear timeline of the school details saved above.</p>
            <div class="education-history-list" data-education-history>
            <?php $fixedHistory = [
                ['Primary Education', 'elementary_school', 'elementary_location', 'elementary_year_started', 'elementary_year_graduated', 'elementary_honors'],
                ['Junior High School', 'junior_high_school', 'junior_high_location', 'junior_high_year_started', 'junior_high_year_completed', 'junior_high_honors'],
                ['Senior High School', 'senior_high_school', 'senior_high_location', 'senior_high_year_started', 'senior_high_year_graduated', 'senior_high_honors'],
            ]; ?>
            <?php $historyCount = 0; foreach ($fixedHistory as [$label, $schoolField, $locationField, $startField, $endField, $honorsField]): if (!empty($educationBackground[$schoolField]) && !($schoolField === 'senior_high_school' && !empty($educationBackground['senior_high_not_applicable']))): $historyCount++; ?>
                <article class="education-summary-entry" data-education-summary="<?= h($schoolField) ?>"><span class="education-summary-level"><?= h($label) ?></span><div><strong data-summary-school><?= h($educationBackground[$schoolField]) ?></strong><small data-summary-location><?= h($educationBackground[$locationField] ?? '') ?><?= !empty($educationBackground[$startField]) || !empty($educationBackground[$endField]) ? ' · ' . h($educationBackground[$startField] ?? '') . '–' . h($educationBackground[$endField] ?? '') : '' ?></small><?php if (!empty($educationBackground[$honorsField])): ?><small><?= h($educationBackground[$honorsField]) ?></small><?php endif; ?></div><a class="text-link" href="#education-background">Edit</a></article>
            <?php endif; endforeach; if (!empty($educationBackground['senior_high_not_applicable'])): ?><article class="education-summary-entry" data-education-summary="senior_high_na"><span class="education-summary-level">Senior High School</span><div><strong>Not applicable</strong><small>Senior high school is not part of this educational pathway.</small></div><a class="button button-small button-quiet" href="#education-senior-high">Edit</a></article><?php endif; ?>
            <?php foreach ($data['education'] as $item): $historyCount++; ?><article class="education-summary-entry"><span class="education-summary-level">Tertiary Education</span><div><strong><?= h($item['school_name']) ?></strong><small><?= h($item['program']) ?><?= !empty($item['major']) ? ' · ' . h($item['major']) : '' ?> · <?= h($item['academic_status'] ?? 'Undergraduate') ?></small></div><a class="text-link" href="#education-background">Edit</a></article><?php endforeach; ?>
            <?php if ($historyCount === 0): ?><p class="muted">Your educational history will appear here as you add records.</p><?php endif; ?>
            </div>
        </section>
    </section>

        <section class="card form-card sj-profile-section sj-profile-skills">
            <h2>Skills for SMARTMATCH</h2>
            <div class="skill-list"><?php foreach ($data['skills'] as $skill): ?><span class="skill-chip"><?= h($skill['name']) ?> <small><?= h($skill['proficiency']) ?></small><?php if (!empty($skill['description'])): ?><span><?= h($skill['description']) ?></span><?php endif; ?></span><?php endforeach; ?><?php if (!$data['skills']): ?><p class="muted">Add skills to your profile to support future matching.</p><?php endif; ?></div>
            <form method="post" class="subform sj-profile-skill-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_skill"><label>Skill name<input name="skill_name" maxlength="120" required></label><label>Proficiency<select name="proficiency"><option>beginner</option><option>intermediate</option><option>advanced</option></select></label><button class="button button-small" type="submit">Add skill</button><label class="sj-profile-skill-description">Skill description <span class="field-help">Optional</span><textarea name="skill_description" data-ai-writing="student_skill_description" maxlength="1200" rows="2"></textarea></label></form>
        </section>
    <section class="card form-card sj-profile-section sj-profile-projects">
        <h2>Projects and Experience</h2>
        <h3>Projects</h3>
        <?php foreach ($data['projects'] as $project): ?><article class="record sj-profile-record-card"><strong><?= h($project['title']) ?></strong><span><?= h($project['role'] ?? '') ?><?= !empty($project['technologies']) ? ' · ' . h($project['technologies']) : '' ?></span><?php if (!empty($project['description'])): ?><p><?= h($project['description']) ?></p><?php endif; ?><?php if (!empty($project['start_date']) || !empty($project['end_date'])): ?><small><?= h($project['start_date'] ?: 'Start date not provided') ?> – <?= h($project['end_date'] ?: 'Present') ?></small><?php endif; ?><?php if (!empty($project['project_url'])): ?><a href="<?= h($project['project_url']) ?>" rel="noopener noreferrer" target="_blank">Portfolio link</a><?php endif; ?></article><?php endforeach; ?>
        <form method="post" class="subform"><?= csrf_field() ?><input type="hidden" name="action" value="add_project"><div class="form-grid"><label>Project title<input name="project_title" maxlength="180" required></label><label>Role<input name="project_role" maxlength="120"></label><label>Technologies<input name="technologies" maxlength="500"></label><label>Portfolio URL<input name="project_url" type="url" maxlength="500" placeholder="https://"></label><label>Start date<input type="date" name="project_start_date"></label><label>End date<input type="date" name="project_end_date"></label></div><label>Description<textarea name="project_description" data-ai-writing="student_project_description" maxlength="3000" rows="3"></textarea></label><button class="button button-small" type="submit">Add project</button></form>
        <h3>Experience</h3>
        <?php foreach ($data['experience'] as $experience): ?><article class="record sj-profile-record-card"><strong><?= h($experience['title']) ?></strong><span><?= h($experience['organization'] ?? '') ?></span><?php if (!empty($experience['description'])): ?><p><?= h($experience['description']) ?></p><?php endif; ?><?php if (!empty($experience['start_date']) || !empty($experience['end_date'])): ?><small><?= h($experience['start_date'] ?: 'Start date not provided') ?> – <?= h($experience['end_date'] ?: 'Present') ?></small><?php endif; ?></article><?php endforeach; ?>
        <form method="post" class="subform"><?= csrf_field() ?><input type="hidden" name="action" value="add_experience"><h3>Previous work or volunteer experience</h3><div class="form-grid"><label>Role / title<input name="experience_title" maxlength="180" required></label><label>Organization<input name="organization" maxlength="180"></label><label>Start date<input type="date" name="experience_start_date"></label><label>End date<input type="date" name="experience_end_date"></label></div><label>Description<textarea name="experience_description" data-ai-writing="student_experience_description" maxlength="3000" rows="3"></textarea></label><button class="button button-small" type="submit">Add experience</button></form>
    </section>
    <section id="biodata-preview" class="biodata-preview sj-profile-section <?= ($data['template'] ?? 'standard') === 'modern' ? 'modern' : '' ?>">
        <h2>Preview</h2><?php $visibility = $data['visibility']; ?><div class="biodata-content"><?php require __DIR__ . '/biodata_content.php'; ?></div>
    </section>
    </div>
    </div>

<?php elseif ($page === 'credentials'): ?>
    <?php $stmt = db()->prepare('SELECT id, category, description, original_name, mime_type, file_size, issued_at, expires_at, created_at FROM tbl_student_credentials WHERE user_id=? ORDER BY created_at DESC'); $stmt->execute([$studentId]); $credentials = $stmt->fetchAll(); ?>
    <section class="page-heading"><p class="eyebrow">PRIVATE DOCUMENT REPOSITORY</p><h1>My credentials</h1><p class="muted">Only you can access your files unless you explicitly share them with an internship application.</p></section>
    <form class="card form-card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="upload_credential"><h2>Upload a document</h2><div class="form-grid"><label>Category<select name="category"><?php foreach (['resume','biodata','enrollment','endorsement','training_certificate','seminar_certificate','academic_record','portfolio','internship_requirement','other'] as $category): ?><option value="<?= h($category) ?>"><?= h(ucwords(str_replace('_', ' ', $category))) ?></option><?php endforeach; ?></select></label><label>File <span class="field-help">PDF/JPEG/PNG/WebP · max 8 MB</span><input type="file" name="credential" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required></label><label>Issue date<input type="date" name="issued_at"></label><label>Expiration date<input type="date" name="expires_at"></label></div><label>Description<input name="description" maxlength="500"></label><button class="button" type="submit">Upload privately</button></form>
    <?php if (!$credentials): ?><div class="empty-state">No credentials uploaded yet.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Document</th><th>Category</th><th>Uploaded</th><th>Size</th><th>Access</th></tr></thead><tbody><?php foreach ($credentials as $credential): ?><tr><td><strong><?= h($credential['original_name']) ?></strong><small><?= h($credential['description'] ?? '') ?></small></td><td><?= h(ucwords(str_replace('_', ' ', $credential['category']))) ?></td><td><?= h(date('M j, Y', strtotime($credential['created_at']))) ?></td><td><?= number_format((int) $credential['file_size'] / 1024) ?> KB</td><td><a class="text-link" href="index.php?page=credential_download&amp;id=<?= (int) $credential['id'] ?>">Download</a><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete_credential"><input type="hidden" name="credential_id" value="<?= (int) $credential['id'] ?>"><button type="submit" class="text-button">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

<?php elseif ($page === 'training'): ?>
    <?php
    $trainingQuery = db()->prepare($user['role'] === 'student'
        ? 'SELECT t.*, u.full_name FROM tbl_student_trainings t JOIN tbl_users u ON u.id=t.user_id WHERE t.user_id=? ORDER BY t.created_at DESC'
        : "SELECT t.*, u.full_name FROM tbl_student_trainings t JOIN tbl_users u ON u.id=t.user_id JOIN tbl_student_profiles sp ON sp.user_id=u.id JOIN tbl_ojt_coordinators c ON c.institution_id=sp.institution_id WHERE c.user_id=? AND c.status='active' AND t.verification_status='pending' ORDER BY t.created_at");
    $trainingQuery->execute([$studentId]);
    $trainingRecords = $trainingQuery->fetchAll();
    $studentTrainingCredentials = [];
    if ($user['role'] === 'student') {
        $credentialQuery = db()->prepare("SELECT id, original_name FROM tbl_student_credentials WHERE user_id=? AND category IN ('training_certificate','seminar_certificate') ORDER BY created_at DESC");
        $credentialQuery->execute([$studentId]);
        $studentTrainingCredentials = $credentialQuery->fetchAll();
    }
    ?>
    <section class="page-heading"><p class="eyebrow"><?= $user['role'] === 'student' ? 'YOUR LEARNING RECORD' : 'INSTITUTIONAL EVIDENCE REVIEW' ?></p><h1><?= $user['role'] === 'student' ? 'Training & certifications' : 'Training verification' ?></h1><p class="muted"><?= $user['role'] === 'student' ? 'Unverified entries are clearly identified and do not count as verified competencies.' : 'Review only students assigned to your institution. Decisions are recorded with reviewer history.' ?></p></section>
    <?php if ($user['role'] === 'student'): ?>
        <div class="two-column">
            <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_training"><h2>Add training record</h2><label>Training title<input name="training_title" maxlength="180" required></label><label>Provider<input name="provider" maxlength="180"></label><div class="form-grid"><label>Category<input name="training_category" maxlength="100"></label><label>Duration (hours)<input name="duration_hours" type="number" step="0.25" min="0"></label><label>Start date<input name="training_start_date" type="date"></label><label>Completion date<input name="training_completion_date" type="date"></label></div><label>Description<textarea name="training_description" maxlength="3000" rows="3"></textarea></label><label>Acquired skills <span class="field-help">comma-separated</span><input name="acquired_skills" maxlength="1000"></label><label>Supporting certificate<select name="training_credential_id"><option value="">No evidence (self-reported)</option><?php foreach ($studentTrainingCredentials as $credential): ?><option value="<?= (int) $credential['id'] ?>"><?= h($credential['original_name']) ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Save training</button></form>
            <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_certification"><h2>Add certification</h2><label>Certification title<input name="certification_title" maxlength="180" required></label><label>Issuing organization<input name="issuer" maxlength="180"></label><div class="form-grid"><label>Issue date<input type="date" name="certification_issued_at"></label><label>Expiration date<input type="date" name="certification_expires_at"></label></div><label>Credential number<input name="credential_number" maxlength="120"></label><label>Supporting document<select name="certification_credential_id"><option value="">None selected</option><?php foreach ($studentTrainingCredentials as $credential): ?><option value="<?= (int) $credential['id'] ?>"><?= h($credential['original_name']) ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Save certification</button></form>
        </div>
    <?php endif; ?>
    <?php if (!$trainingRecords && $user['role'] === 'coordinator'): ?><div class="empty-state">No training records are waiting for review.</div><?php elseif (!$trainingRecords): ?><div class="empty-state">No training records yet. Add one above.</div>    <?php else: ?><div class="table-wrap"><table><thead><tr><th>Record</th><?php if ($user['role'] === 'coordinator'): ?><th>Student</th><th>Evidence</th><?php endif; ?><th>Provider</th><th>Status</th><?php if ($user['role'] === 'coordinator'): ?><th>Review</th><?php endif; ?></tr></thead><tbody><?php foreach ($trainingRecords as $training): ?><tr><td><strong><?= h($training['title']) ?></strong><small><?= h($training['category'] ?? '') ?><?= $training['duration_hours'] ? ' · ' . h($training['duration_hours']) . ' hours' : '' ?></small></td><?php if ($user['role'] === 'coordinator'): ?><td><?= h($training['full_name']) ?></td><td><?php if ($training['credential_id']): ?><a class="text-link" href="index.php?page=credential_download&amp;id=<?= (int) $training['credential_id'] ?>">Review certificate</a><?php else: ?>No evidence<?php endif; ?></td><?php endif; ?><td><?= h($training['provider'] ?? '—') ?></td><td><span class="badge <?= $training['verification_status'] === 'verified' ? 'approved' : ($training['verification_status'] === 'rejected' ? 'rejected' : 'pending') ?>"><?= h(ucwords(str_replace('_', ' ', $training['verification_status']))) ?></span></td><?php if ($user['role'] === 'coordinator'): ?><td><form method="post" class="inline-review"><?= csrf_field() ?><input type="hidden" name="action" value="verify_training"><input type="hidden" name="training_id" value="<?= (int) $training['id'] ?>"><input name="notes" placeholder="Review note" maxlength="1000" aria-label="Review note"><button class="button button-small" name="decision" value="verified">Verify</button><button class="button button-small button-danger" name="decision" value="rejected">Reject</button></form></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    <?php if ($user['role'] === 'student'): ?><h2 class="section-title">My certifications</h2><?php $certQuery = db()->prepare('SELECT title, issuer, issued_at, expires_at, verification_status FROM tbl_student_certifications WHERE user_id=? ORDER BY issued_at DESC'); $certQuery->execute([$studentId]); $certs = $certQuery->fetchAll(); ?><?php if ($certs): ?><div class="table-wrap"><table><thead><tr><th>Certification</th><th>Issuer</th><th>Dates</th><th>Status</th></tr></thead><tbody><?php foreach ($certs as $cert): ?><tr><td><?= h($cert['title']) ?></td><td><?= h($cert['issuer'] ?? '—') ?></td><td><?= h($cert['issued_at'] ?? '—') ?><?= $cert['expires_at'] ? ' – ' . h($cert['expires_at']) : '' ?></td><td><?= h(ucwords(str_replace('_', ' ', $cert['verification_status']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php endif; ?>

<?php elseif ($page === 'messages'): ?>
    <?php
    $messageSearch = trim((string) ($_GET['q'] ?? ''));
    $inbox = db()->prepare('SELECT c.id, c.subject, c.updated_at, (SELECT m.body FROM tbl_messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) AS last_message, (SELECT u.full_name FROM tbl_conversation_participants cp JOIN tbl_users u ON u.id=cp.user_id WHERE cp.conversation_id=c.id AND cp.user_id<>? LIMIT 1) AS other_name, (SELECT COUNT(*) FROM tbl_messages m JOIN tbl_conversation_participants cp ON cp.conversation_id=m.conversation_id AND cp.user_id=? WHERE m.conversation_id=c.id AND m.sender_id<>? AND (cp.last_read_at IS NULL OR m.created_at>cp.last_read_at)) AS unread FROM tbl_conversations c JOIN tbl_conversation_participants mine ON mine.conversation_id=c.id AND mine.user_id=? WHERE mine.user_id=? AND ((? = \'\') OR c.subject LIKE ? OR EXISTS (SELECT 1 FROM tbl_conversation_participants cp JOIN tbl_users u ON u.id=cp.user_id WHERE cp.conversation_id=c.id AND cp.user_id<>? AND u.full_name LIKE ?)) ORDER BY c.updated_at DESC');
    $like = '%' . $messageSearch . '%';
    $inbox->execute([$studentId, $studentId, $studentId, $studentId, $studentId, $messageSearch, $like, $studentId, $like]);
    $threads = $inbox->fetchAll();
    $selectedConversationId = filter_var($_GET['conversation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $selectedThread = null;
    $threadMessages = [];
    if ($selectedConversationId) {
        $selected = db()->prepare('SELECT c.id, c.subject FROM tbl_conversations c JOIN tbl_conversation_participants cp ON cp.conversation_id=c.id WHERE c.id=? AND cp.user_id=?');
        $selected->execute([$selectedConversationId, $studentId]);
        $selectedThread = $selected->fetch();
        if (!$selectedThread) {
            throw new RuntimeException('Conversation not found or you are not a participant.');
        }
        $messages = db()->prepare('SELECT m.id, m.sender_id, m.body, m.created_at, u.full_name FROM tbl_messages m JOIN tbl_users u ON u.id=m.sender_id WHERE m.conversation_id=? ORDER BY m.id');
        $messages->execute([$selectedConversationId]);
        $threadMessages = $messages->fetchAll();
        db()->prepare('UPDATE tbl_conversation_participants SET last_read_at=NOW() WHERE conversation_id=? AND user_id=?')->execute([$selectedConversationId, $studentId]);
    }
    $applicationQuery = match ($user['role']) {
        'student' => db()->prepare('SELECT a.id, i.title, c.name AS participant FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id JOIN tbl_companies c ON c.id=i.company_id WHERE a.student_id=? ORDER BY a.created_at DESC'),
        'employer' => db()->prepare('SELECT a.id, i.title, u.full_name AS participant FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id JOIN tbl_users u ON u.id=a.student_id WHERE i.company_id=? ORDER BY a.created_at DESC'),
        'coordinator' => db()->prepare('SELECT a.id, i.title, u.full_name AS participant FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id JOIN tbl_users u ON u.id=a.student_id JOIN tbl_student_profiles sp ON sp.user_id=u.id JOIN tbl_ojt_coordinators oc ON oc.institution_id=sp.institution_id WHERE oc.user_id=? AND oc.status=\'active\' ORDER BY a.created_at DESC'),
        default => db()->prepare('SELECT a.id, i.title, c.name AS participant FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id JOIN tbl_companies c ON c.id=i.company_id ORDER BY a.created_at DESC LIMIT 100'),
    };
    if ($user['role'] === 'admin') {
        $applicationQuery->execute();
    } elseif ($user['role'] === 'employer') {
        $applicationQuery->execute([current_company_id($studentId)]);
    } else {
        $applicationQuery->execute([$studentId]);
    }
    $messageApplications = $applicationQuery->fetchAll();
    ?>
    <section class="page-heading"><p class="eyebrow">SMARTMESSAGE</p><h1>Internship messages</h1><p class="muted">Only application participants and authorized institutional coordinators can access each conversation.</p></section>
    <div class="two-column messaging-layout">
        <section class="card">
            <form method="get" class="search-form"><input type="hidden" name="page" value="messages"><label class="sr-only" for="message-search">Search conversations</label><input id="message-search" name="q" value="<?= h($messageSearch) ?>" placeholder="Search conversations"><button class="button button-small">Search</button></form>
            <?php if (!$threads): ?><div class="empty-state">No conversations found.</div><?php else: ?><div class="thread-list"><?php foreach ($threads as $thread): ?><a class="thread-link <?= $selectedConversationId === (int) $thread['id'] ? 'selected' : '' ?>" href="index.php?page=messages&amp;conversation_id=<?= (int) $thread['id'] ?>"><strong><?= h($thread['subject']) ?></strong><span><?= h($thread['other_name'] ?? 'Participant') ?> · <?= h($thread['last_message'] ?? 'No messages yet') ?></span><?php if ((int) $thread['unread'] > 0): ?><b class="unread-count"><?= (int) $thread['unread'] ?></b><?php endif; ?></a><?php endforeach; ?></div><?php endif; ?>
        </section>
        <section class="card">
            <?php if ($selectedThread): ?><h2><?= h($selectedThread['subject']) ?></h2><div id="message-list" class="message-list" data-conversation="<?= (int) $selectedThread['id'] ?>"><?php foreach ($threadMessages as $message): ?><article class="message <?= (int) $message['sender_id'] === $studentId ? 'mine' : '' ?>" data-id="<?= (int) $message['id'] ?>"><strong><?= h($message['full_name']) ?></strong><p><?= nl2br(h($message['body'])) ?></p><small><?= h(date('M j, Y H:i', strtotime($message['created_at']))) ?></small><?php if ((int) $message['sender_id'] !== $studentId): ?><details><summary>Report</summary><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="report_message"><input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>"><input name="reason" maxlength="1000" placeholder="Why report this message?" required><button class="text-button">Submit report</button></form></details><?php endif; ?></article><?php endforeach; ?></div><form method="post" class="reply-form"><?= csrf_field() ?><input type="hidden" name="action" value="send_message"><input type="hidden" name="conversation_id" value="<?= (int) $selectedThread['id'] ?>"><label>Reply<textarea name="message" rows="3" maxlength="10000" required></textarea></label><button class="button button-small">Send message</button></form><?php else: ?><h2>Start a conversation</h2><p class="muted">Choose an application to contact its authorized participant.</p><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create_conversation"><label>Application<select name="application_id" required><option value="">Choose application</option><?php foreach ($messageApplications as $application): ?><option value="<?= (int) $application['id'] ?>">#<?= (int) $application['id'] ?> · <?= h($application['title']) ?> — <?= h($application['participant']) ?></option><?php endforeach; ?></select></label><label>Subject<input name="subject" maxlength="180" required></label><label>Message<textarea name="message" rows="5" maxlength="10000" required></textarea></label><button class="button" type="submit">Start conversation</button></form><?php endif; ?>
        </section>
    </div>
    <?php if ($selectedThread): ?><script>
    (() => {
        const list = document.getElementById('message-list');
        let lastId = Math.max(0, ...Array.from(list.querySelectorAll('[data-id]')).map((item) => Number(item.dataset.id)));
        const poll = async () => {
            try {
                const response = await fetch(`index.php?page=message_poll&conversation_id=${list.dataset.conversation}&after=${lastId}`, {headers: {'Accept': 'application/json'}});
                if (!response.ok) return;
                const data = await response.json();
                for (const message of data.messages) {
                    if (Number(message.id) <= lastId) continue;
                    const article = document.createElement('article');
                    article.className = `message${Number(message.mine) === 1 ? ' mine' : ''}`;
                    const sender = document.createElement('strong');
                    sender.textContent = message.sender_name;
                    const content = document.createElement('p');
                    content.textContent = message.body;
                    const timestamp = document.createElement('small');
                    timestamp.textContent = message.created_at;
                    article.dataset.id = message.id;
                    article.append(sender, content, timestamp);
                    list.append(article);
                    lastId = Number(message.id);
                }
                if (data.messages.length) list.scrollTop = list.scrollHeight;
            } catch (error) {
                console.error('SMARTMESSAGE polling failed.', error);
            }
        };
        window.smartjobPollMessages = poll;
        window.setInterval(poll, 5000);
    })();
    </script><?php endif; ?>

<?php elseif ($page === 'email'): ?>
    <?php
    $drafts = db()->prepare('SELECT e.id, e.recipient_email, e.subject, e.status, e.error_message, e.created_at, i.title FROM tbl_email_messages e LEFT JOIN tbl_applications a ON a.id=e.application_id LEFT JOIN tbl_internship_opportunities i ON i.id=a.internship_id WHERE e.sender_id=? ORDER BY e.created_at DESC LIMIT 100');
    $drafts->execute([$studentId]);
    $emailRecords = $drafts->fetchAll();
    $templates = db()->query('SELECT template_key, name, subject, body FROM tbl_email_templates WHERE active=1 ORDER BY name')->fetchAll();
    $emailApps = db()->prepare(match ($user['role']) {
        'student' => 'SELECT a.id, i.title FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id WHERE a.student_id=? ORDER BY a.created_at DESC',
        'employer' => 'SELECT a.id, i.title FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id WHERE i.company_id=? ORDER BY a.created_at DESC',
        'coordinator' => "SELECT a.id, i.title FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id JOIN tbl_student_profiles sp ON sp.user_id=a.student_id JOIN tbl_ojt_coordinators oc ON oc.institution_id=sp.institution_id WHERE oc.user_id=? AND oc.status='active' ORDER BY a.created_at DESC",
        default => 'SELECT a.id, i.title FROM tbl_applications a JOIN tbl_internship_opportunities i ON i.id=a.internship_id ORDER BY a.created_at DESC LIMIT 100',
    });
    if ($user['role'] === 'admin') {
        $emailApps->execute();
    } else {
        $emailApps->execute([$user['role'] === 'employer' ? current_company_id($studentId) : $studentId]);
    }
    $emailApplications = $emailApps->fetchAll();
    ?>
    <section class="page-heading"><p class="eyebrow">SMARTMAIL</p><h1>Email from SMARTJOB</h1><p class="muted">Recipients are resolved from an authorized application. Drafts remain drafts until a configured SMTP server accepts them.</p></section>
    <form class="card form-card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_email_draft"><h2>Compose email</h2><label>Related application<select name="application_id" required><option value="">Choose application</option><?php foreach ($emailApplications as $application): ?><option value="<?= (int) $application['id'] ?>">#<?= (int) $application['id'] ?> · <?= h($application['title']) ?></option><?php endforeach; ?></select></label><label>Email template<select id="email-template"><option value="">Start from blank</option><?php foreach ($templates as $template): ?><option value="<?= h($template['template_key']) ?>" data-subject="<?= h($template['subject']) ?>" data-body="<?= h($template['body']) ?>"><?= h($template['name']) ?></option><?php endforeach; ?></select></label><label>Subject<input id="email-subject" name="email_subject" maxlength="250" required></label><label>Message<textarea id="email-body" name="email_body" rows="6" maxlength="15000" required></textarea></label><button class="button" type="submit">Save as draft</button><p class="muted">SMTP configuration is server-side only. No email will be sent until you explicitly send a saved draft.</p></form>
    <section class="card"><h2>My email history</h2><?php if (!$emailRecords): ?><p class="muted">No drafts or email activity yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Subject</th><th>Recipient</th><th>Opportunity</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($emailRecords as $record): ?><tr><td><strong><?= h($record['subject']) ?></strong><small><?= h(date('M j, Y H:i', strtotime($record['created_at']))) ?></small><?php if ($record['error_message']): ?><small><?= h($record['error_message']) ?></small><?php endif; ?></td><td><?= h($record['recipient_email']) ?></td><td><?= h($record['title'] ?? '—') ?></td><td><span class="badge <?= $record['status'] === 'sent' ? 'approved' : ($record['status'] === 'failed' ? 'rejected' : 'pending') ?>"><?= h(ucfirst($record['status'])) ?></span></td><td><?php if (in_array($record['status'], ['draft','failed'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_email"><input type="hidden" name="email_id" value="<?= (int) $record['id'] ?>"><button class="button button-small">Send via SMTP</button></form><?php else: ?><span class="muted">Recipient delivery not confirmed</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    <script>
    const emailTemplate = document.getElementById('email-template');
    if (emailTemplate) emailTemplate.addEventListener('change', () => {
        const selected = emailTemplate.selectedOptions[0];
        if (selected.dataset.subject) document.getElementById('email-subject').value = selected.dataset.subject;
        if (selected.dataset.body) document.getElementById('email-body').value = selected.dataset.body;
    });
    </script>
<?php elseif ($page === 'account_settings'): ?>
    <?php
    $settingsAccount = $settingsAccount ?? $user;
    $accountPhoto = null;
    if ($user['role'] === 'student') {
        $accountPhotoQuery = db()->prepare('SELECT profile_photo_key FROM tbl_student_profiles WHERE user_id=?');
        $accountPhotoQuery->execute([$studentId]);
        $accountPhotoKey = $accountPhotoQuery->fetchColumn();
        $accountPhoto = ProfilePhotoStorage::dataUri(is_string($accountPhotoKey) ? $accountPhotoKey : null);
    }
    $settingsRoleLabel = [
        'student' => 'Student account',
        'employer' => 'Company account',
        'coordinator' => 'OJT coordinator account',
        'admin' => 'Administrator account',
    ][$user['role']] ?? 'SMARTJOB account';
    ?>
    <section class="page-heading"><p class="eyebrow">YOUR ACCOUNT</p><h1>Account settings</h1><p class="muted">Manage your account security<?= $user['role'] === 'student' ? ', profile photo, and contact information' : '' ?>. Password changes require your current password.</p></section>
    <?php if ($user['role'] === 'student'): ?><section class="card settings-section">
        <h2>Profile picture</h2><p class="muted">JPG or PNG, up to 5 MB. The square preview shows the crop used on your profile and biodata.</p>
        <div class="photo-settings">
            <div class="avatar-frame avatar-large"><?php if ($accountPhoto): ?><img id="photo-preview" src="<?= h($accountPhoto) ?>" alt="Current profile photo"><?php else: ?><img id="photo-preview" src="" alt="Selected profile photo" hidden><span id="photo-placeholder" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span><?php endif; ?></div>
            <div><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="upload_profile_photo"><label>Choose a photo<input id="profile-photo-input" type="file" name="profile_photo" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required></label><p class="field-help photo-validation" id="photo-validation" role="status"></p><button class="button button-small" type="submit">Save photo</button></form>
            <?php if ($accountPhoto): ?><form method="post" class="photo-remove-form"><?= csrf_field() ?><input type="hidden" name="action" value="remove_profile_photo"><button class="button button-small button-danger" type="submit">Remove photo</button></form><?php endif; ?></div>
        </div>
    </section><?php endif; ?>
    <?php if ($user['role'] === 'student'): ?><div class="settings-grid">
        <form class="card settings-section" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_account_contacts"><h2>Contact information</h2><label>Registered email address<input name="email" type="email" maxlength="190" autocomplete="email" value="<?= h($settingsAccount['email']) ?>" required></label><label>Philippine mobile number <span class="field-help">+639XXXXXXXXX</span><input name="phone_number" type="tel" maxlength="20" autocomplete="tel" value="<?= h($settingsAccount['phone_number'] ?? '') ?>" placeholder="+639XXXXXXXXX" required></label><label>Current password<input name="current_password" type="password" maxlength="72" autocomplete="current-password" required></label><button class="button" type="submit">Save contact information</button><p class="field-help settings-note">If verification is enabled, changing a verified contact will require verification before the account is active again.</p></form>
        <form id="security" class="card settings-section" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_account_password"><h2>Security</h2><label>Current password<input name="current_password" type="password" maxlength="72" autocomplete="current-password" required></label><label>New password <span class="field-help">12–72 bytes</span><input name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>Confirm new password<input name="confirm_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="button" type="submit">Change password</button></form>
    </div><?php else: ?>
        <form id="security" class="card settings-section" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_account_password"><h2>Security</h2><p class="muted">Update your password using your current password for confirmation.</p><label>Current password<input name="current_password" type="password" maxlength="72" autocomplete="current-password" required></label><label>New password <span class="field-help">12–72 bytes</span><input name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>Confirm new password<input name="confirm_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="button" type="submit">Change password</button></form>
    <?php endif; ?>
    <section id="account-profile" class="card account-summary"><p class="eyebrow">ACCOUNT INFORMATION</p><p><strong><?= h($settingsAccount['full_name']) ?></strong></p><p><?= h($settingsAccount['email']) ?><?= !empty($settingsAccount['phone_number']) ? ' · ' . h($settingsAccount['phone_number']) : '' ?></p><p class="muted"><?= h($settingsRoleLabel) ?> · Passwords are securely hashed.</p></section>
    <script>
    (() => {
        const input = document.getElementById('profile-photo-input');
        const preview = document.getElementById('photo-preview');
        const placeholder = document.getElementById('photo-placeholder');
        const message = document.getElementById('photo-validation');
        if (!input || !preview || !message) return;
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                input.value = '';
                message.textContent = 'Choose a JPG or PNG image no larger than 5 MB.';
                return;
            }
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                preview.src = String(reader.result);
                preview.hidden = false;
                if (placeholder) placeholder.hidden = true;
                message.textContent = 'Preview ready. The image will be shown as a square crop.';
            });
            reader.addEventListener('error', () => { message.textContent = 'The selected image could not be previewed.'; });
            reader.readAsDataURL(file);
        });
    })();
    </script>
<?php endif; ?>
