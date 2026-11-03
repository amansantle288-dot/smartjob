<div class="biodata-header"><?php if (!empty($data['photo_data_uri'])): ?><div class="biodata-photo-area"><img class="biodata-photo" src="<?= h($data['photo_data_uri']) ?>" alt="Profile photo"></div><?php endif; ?><div><h1><?= h($data['full_name']) ?></h1>
<div class="meta"><?= h($data['email']) ?><?= $data['phone'] ? ' · ' . h($data['phone']) : '' ?><?= $data['city'] || $data['province'] ? ' · ' . h(trim(($data['city'] ?? '') . ', ' . ($data['province'] ?? ''), ', ')) : '' ?></div>
<?php if (in_array('date_of_birth', $visibility, true) && $data['date_of_birth']): ?><p class="meta">Date of birth: <?= h($data['date_of_birth']) ?></p><?php endif; ?>
<?php if ($data['address']): ?><p class="meta"><?= h($data['address']) ?></p><?php endif; ?>
</div></div>
<?php if ($data['summary']): ?><div class="summary"><?= nl2br(h($data['summary'])) ?></div><?php endif; ?>
<?php $background = $data['education_background']; $hasBackground = count(array_filter($background, static fn ($value, $key): bool => !in_array($key, ['user_id', 'updated_at'], true) && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH)) > 0; ?>
<?php if (in_array('education', $visibility, true) && ($data['education'] || $data['program'] || $hasBackground)): ?>
<section class="biodata-education" data-biodata-education>
    <h2>Education</h2>
    <?php foreach ([
        ['primary', 'Primary education', 'elementary_school', 'elementary_location', 'elementary_year_started', 'elementary_year_graduated', null, 'elementary_honors', 0],
        ['junior_high', 'Junior high school', 'junior_high_school', 'junior_high_location', 'junior_high_year_started', 'junior_high_year_completed', null, 'junior_high_honors', 1],
        ['senior_high', 'Senior high school', 'senior_high_school', 'senior_high_location', 'senior_high_year_started', 'senior_high_year_graduated', 'senior_high_strand', 'senior_high_honors', 2],
    ] as [$level, $label, $schoolKey, $locationKey, $startKey, $endKey, $programKey, $honorsKey, $order]): ?>
        <?php if (!empty($background[$schoolKey]) && !($level === 'senior_high' && !empty($background['senior_high_not_applicable']))): ?>
            <div class="item" data-biodata-level="<?= h($level) ?>" data-biodata-order="<?= (int) $order ?>">
                <strong data-biodata-school><?= h($background[$schoolKey]) ?></strong>
                <div><span data-biodata-label><?= h($label) ?></span><?php if (!empty($background[$programKey])): ?> · <span data-biodata-program><?= h($background[$programKey]) ?></span><?php endif; ?></div>
                <div class="sub"><span data-biodata-location><?= h($background[$locationKey] ?? '') ?></span><span data-biodata-years><?= !empty($background[$startKey]) || !empty($background[$endKey]) ? ' · ' . h($background[$startKey] ?? '') . '–' . h($background[$endKey] ?? '') : '' ?></span></div>
                <div class="sub" data-biodata-honors <?= empty($background[$honorsKey]) ? 'hidden' : '' ?>><?= h($background[$honorsKey] ?? '') ?></div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="item" data-biodata-level="senior_high_na" data-biodata-order="2" <?= empty($background['senior_high_not_applicable']) ? 'hidden' : '' ?>><strong>Senior high school</strong><div>Not applicable to this educational pathway.</div></div>
    <?php foreach ($data['education'] as $item): ?>
        <?php $itemStatus = ($item['academic_status'] ?? '') === 'undergraduate' && !empty($item['end_date']) ? 'graduated' : ($item['academic_status'] ?? 'tertiary'); ?>
        <div class="item" data-biodata-order="3">
            <strong><?= h($item['program']) ?></strong>
            <div><?= h($item['school_name']) ?><?= !empty($item['major']) ? ' · ' . h($item['major']) : '' ?><?= !empty($item['department']) ? ' · ' . h($item['department']) : '' ?><?= !empty($item['year_level']) ? ' · ' . h($item['year_level']) : '' ?></div>
            <div class="sub"><?= h(ucwords(str_replace('_', ' ', $itemStatus))) ?><?= !empty($item['start_date']) ? ' · ' . h(substr((string) $item['start_date'], 0, 4)) : '' ?><?= !empty($item['end_date']) ? '–' . h(substr((string) $item['end_date'], 0, 4)) : (!empty($item['expected_graduation_year']) ? '–' . h((string) $item['expected_graduation_year']) : '') ?><?= !empty($item['location']) ? ' · ' . h($item['location']) : '' ?></div>
            <?php if (!empty($item['achievements'])): ?><div class="sub"><?= h($item['achievements']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$data['education'] && $data['program']): ?><div class="item" data-biodata-order="3"><strong><?= h($data['program']) ?></strong><div><?= h($data['year_level'] ?? '') ?></div></div><?php endif; ?>
</section>
<?php endif; ?>
<?php if (in_array('experience', $visibility, true) && $data['experience']): ?><h2>Experience</h2><?php foreach ($data['experience'] as $item): ?><div class="item"><strong><?= h($item['title']) ?></strong><div class="sub"><?= h($item['organization'] ?? '') ?></div><div><?= nl2br(h($item['description'] ?? '')) ?></div></div><?php endforeach; ?><?php endif; ?>
<?php if (in_array('projects', $visibility, true) && $data['projects']): ?><h2>Projects</h2><?php foreach ($data['projects'] as $item): ?><div class="item"><strong><?= h($item['title']) ?></strong><?= $item['role'] ? ' · ' . h($item['role']) : '' ?><div><?= nl2br(h($item['description'] ?? '')) ?></div><?php if ($item['technologies']): ?><div class="sub">Technologies: <?= h($item['technologies']) ?></div><?php endif; ?><?php if ($item['project_url']): ?><div class="sub"><?= h($item['project_url']) ?></div><?php endif; ?></div><?php endforeach; ?><?php endif; ?>
<?php if (in_array('skills', $visibility, true) && $data['skills']): ?><h2>Skills</h2><div><?= h(implode(' · ', array_map(static fn ($skill): string => $skill['name'] . ' (' . $skill['proficiency'] . ')', $data['skills']))) ?></div><?php endif; ?>
<?php if (in_array('training', $visibility, true) && $data['training']): ?><h2>Training</h2><?php foreach ($data['training'] as $item): ?><div class="item"><strong><?= h($item['title']) ?></strong><div class="sub"><?= h($item['provider'] ?? '') ?> · <?= h(str_replace('_', ' ', $item['verification_status'])) ?><?= $item['completion_date'] ? ' · ' . h($item['completion_date']) : '' ?></div></div><?php endforeach; ?><?php endif; ?>
<?php if (in_array('certifications', $visibility, true) && $data['certifications']): ?><h2>Certifications</h2><?php foreach ($data['certifications'] as $item): ?><div class="item"><strong><?= h($item['title']) ?></strong><div class="sub"><?= h($item['issuer'] ?? '') ?> · <?= h(str_replace('_', ' ', $item['verification_status'])) ?><?= $item['issued_at'] ? ' · ' . h($item['issued_at']) : '' ?></div></div><?php endforeach; ?><?php endif; ?>
