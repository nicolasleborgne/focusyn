<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;

/**
 * Fait métier révolu, exprimé au passé (« NoteWasPublished »).
 *
 * C'est le seul canal de communication autorisé entre contextes bornés : un
 * contexte publie ce qui lui est arrivé, il n'ordonne jamais à un autre ce
 * qu'il doit faire.
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
