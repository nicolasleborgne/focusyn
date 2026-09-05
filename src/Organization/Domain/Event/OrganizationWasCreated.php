<?php

declare(strict_types=1);

namespace App\Organization\Domain\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * Contrat public : primitives uniquement (voir UserWasRegistered).
 */
final readonly class OrganizationWasCreated implements DomainEvent
{
    public function __construct(
        public string $organizationId,
        public string $name,
        public bool $personal,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
