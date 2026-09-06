<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final readonly class ReminderWasScheduled implements DomainEvent
{
    public function __construct(
        public string $reminderId,
        public string $organizationId,
        public string $subject,
        public string $label,
        public DateTimeImmutable $dueAt,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
