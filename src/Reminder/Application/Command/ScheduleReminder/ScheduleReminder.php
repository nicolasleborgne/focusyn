<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\ScheduleReminder;

final readonly class ScheduleReminder
{
    public function __construct(
        public string $subject,
        public string $label,
        public string $dueAt,
    ) {
    }
}
