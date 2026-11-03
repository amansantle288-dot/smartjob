<?php
declare(strict_types=1);

final class HiringCountdown
{
    private const TIMEZONE = 'Asia/Manila';

    /**
     * @return array{state: string, label: string, badge_class: string, days_remaining: int, can_apply: bool}
     */
    public static function status(string $deadline, string $postingStatus, ?string $today = null): array
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $todayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $today ?? (new DateTimeImmutable('now', $timezone))->format('Y-m-d'), $timezone);
        $deadlineDate = DateTimeImmutable::createFromFormat('!Y-m-d', $deadline, $timezone);
        if (!$todayDate || !$deadlineDate || $todayDate->format('Y-m-d') !== ($today ?? $todayDate->format('Y-m-d')) || $deadlineDate->format('Y-m-d') !== $deadline) {
            throw new RuntimeException('The internship deadline is invalid.');
        }

        $daysRemaining = max(0, (int) $todayDate->diff($deadlineDate)->format('%r%a'));
        if ($postingStatus !== 'published') {
            return match ($postingStatus) {
                'paused' => self::result('paused', 'Hiring Paused', 'hiring-paused', $daysRemaining, false),
                'closed' => self::result('closed', 'Hiring Closed', 'hiring-closed', $daysRemaining, false),
                'draft' => self::result('draft', 'Draft', 'hiring-draft', $daysRemaining, false),
                default => self::result('closed', 'Hiring Closed', 'hiring-closed', $daysRemaining, false),
            };
        }

        if ($deadlineDate < $todayDate) {
            return self::result('closed', 'Hiring Closed', 'hiring-closed', 0, false);
        }
        if ($daysRemaining === 0) {
            return self::result('deadline_today', 'Deadline Today', 'hiring-today', 0, true);
        }
        if ($daysRemaining <= 3) {
            return self::result('closing_soon', 'Closing Soon', 'hiring-closing-soon', $daysRemaining, true);
        }

        return self::result('open', 'Open for Applications', 'hiring-open', $daysRemaining, true);
    }

    public static function postedDate(string $publishedAt): string
    {
        try {
            return (new DateTimeImmutable($publishedAt, new DateTimeZone(self::TIMEZONE)))->format('F j, Y');
        } catch (Throwable $exception) {
            throw new RuntimeException('The internship posting date is invalid.', 0, $exception);
        }
    }

    public static function publishTimestamp(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
    }

    public static function deadlineFromDuration(string $publishedAt, int $durationDays): string
    {
        if ($durationDays < 1 || $durationDays > 90) {
            throw new RuntimeException('Choose a valid hiring duration.');
        }
        try {
            $publishedDate = (new DateTimeImmutable($publishedAt, new DateTimeZone(self::TIMEZONE)))->setTime(0, 0);
        } catch (Throwable $exception) {
            throw new RuntimeException('The internship publication date is invalid.', 0, $exception);
        }

        return $publishedDate->modify('+' . $durationDays . ' days')->format('Y-m-d');
    }

    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
    }

    /**
     * @return array{state: string, label: string, badge_class: string, days_remaining: int, can_apply: bool}
     */
    private static function result(string $state, string $label, string $badgeClass, int $daysRemaining, bool $canApply): array
    {
        return [
            'state' => $state,
            'label' => $label,
            'badge_class' => $badgeClass,
            'days_remaining' => $daysRemaining,
            'can_apply' => $canApply,
        ];
    }
}
