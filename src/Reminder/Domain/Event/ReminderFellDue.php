<?php

declare(strict_types=1);

namespace App\Reminder\Domain\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * L'échéance est arrivée et la notification est partie.
 *
 * Publié après coup, pas avant : un consommateur qui l'entend sait que le
 * porteur a été prévenu, il n'a pas à le prévenir lui-même.
 */
final readonly class ReminderFellDue implements DomainEvent
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
