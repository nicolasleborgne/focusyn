<?php

declare(strict_types=1);

namespace App\Reminder\Application\Query;

use DateTimeImmutable;

final readonly class ReminderView
{
    public function __construct(
        public string $id,
        public string $subject,
        public string $label,
        public DateTimeImmutable $dueAt,
        public bool $notified,
    ) {
    }
}
