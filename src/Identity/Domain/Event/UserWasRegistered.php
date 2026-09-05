<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * Un compte vient d'être créé.
 *
 * Cet événement franchit la frontière du contexte — Organization s'en sert pour
 * ouvrir l'espace personnel — et ne transporte donc que des primitives. Un
 * consommateur qui devrait importer `UserId` pour le lire dépendrait des types
 * internes d'Identity, ce que la règle d'étanchéité interdit.
 */
final readonly class UserWasRegistered implements DomainEvent
{
    public function __construct(
        public string $userId,
        public string $email,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
