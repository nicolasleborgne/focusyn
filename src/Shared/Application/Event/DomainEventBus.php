<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\DomainEvent;

/**
 * Publication des événements de domaine.
 *
 * C'est la seule voie par laquelle un contexte apprend ce qui est arrivé chez
 * un autre. Les dépôts relâchent les événements accumulés par un agrégat et
 * les confient à ce port après l'enregistrement — jamais avant : un événement
 * annonce un fait acquis, pas une intention.
 */
interface DomainEventBus
{
    public function publish(DomainEvent ...$events): void;
}
