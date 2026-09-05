<?php

declare(strict_types=1);

namespace App\Task\Domain\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/** Contrat public : primitives uniquement. */
final readonly class TaskListWasOpened implements DomainEvent
{
    public function __construct(
        public string $taskListId,
        public string $organizationId,
        public string $name,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
