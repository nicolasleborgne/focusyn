<?php

declare(strict_types=1);

namespace App\Notebook\Domain\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * Contrat public : primitives uniquement.
 */
final readonly class NoteWasWritten implements DomainEvent
{
    public function __construct(
        public string $noteId,
        public string $organizationId,
        public string $authorId,
        public string $title,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
