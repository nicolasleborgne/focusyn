<?php

declare(strict_types=1);

namespace App\Reminder\Infrastructure\Review;

use App\Reminder\Application\Command\ScheduleReminder\ScheduleReminder;
use App\Reminder\Application\Command\ScheduleReminder\ScheduleReminderHandler;
use App\Shared\Application\Reminder\ReminderScheduler;
use DateTimeImmutable;

/**
 * Comme les autres portes de contexte : appel direct du gestionnaire, pour que
 * les rappels d'un même report tiennent ou tombent ensemble.
 */
final readonly class CommandReminderScheduler implements ReminderScheduler
{
    public function __construct(
        private ScheduleReminderHandler $schedule,
    ) {
    }

    public function scheduleFor(string $subject, string $label, DateTimeImmutable $dueAt): void
    {
        ($this->schedule)(new ScheduleReminder($subject, $label, $dueAt->format(\DATE_ATOM)));
    }
}
