<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/services/HiringCountdown.php';

$open = HiringCountdown::status('2026-11-10', 'published', '2026-11-06');
$closingSoon = HiringCountdown::status('2026-11-09', 'published', '2026-11-06');
$deadlineToday = HiringCountdown::status('2026-11-06', 'published', '2026-11-06');
$expired = HiringCountdown::status('2026-11-05', 'published', '2026-11-06');
$paused = HiringCountdown::status('2026-11-10', 'paused', '2026-11-06');

if ($open['state'] !== 'open' || $open['days_remaining'] !== 4 || !$open['can_apply']) {
    throw new RuntimeException('Open internship countdown is incorrect.');
}
if ($closingSoon['state'] !== 'closing_soon' || $closingSoon['days_remaining'] !== 3 || !$closingSoon['can_apply']) {
    throw new RuntimeException('Closing-soon countdown is incorrect.');
}
if ($deadlineToday['state'] !== 'deadline_today' || $deadlineToday['days_remaining'] !== 0 || !$deadlineToday['can_apply']) {
    throw new RuntimeException('The deadline date must remain open for applications through that calendar day.');
}
if ($expired['state'] !== 'closed' || $expired['days_remaining'] !== 0 || $expired['can_apply']) {
    throw new RuntimeException('An expired internship must be closed without negative remaining days.');
}
if ($paused['state'] !== 'paused' || $paused['can_apply']) {
    throw new RuntimeException('A manually paused internship must override the calculated deadline state.');
}
if (HiringCountdown::deadlineFromDuration('2026-11-03 14:30:00', 30) !== '2026-12-03') {
    throw new RuntimeException('Hiring deadline was not calculated from the original publication date.');
}

try {
    HiringCountdown::status('2026-02-30', 'published', '2026-02-01');
    throw new RuntimeException('An invalid deadline was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'The internship deadline is invalid.') {
        throw $exception;
    }
}

echo "Hiring countdown states, deadline inclusivity, and duration calculations verified.\n";
