<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command\ErasePersonalData;

use App\Shared\Application\Privacy\PersonalDataContributor;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Efface le contenu de l'organisation courante.
 *
 * Le compte lui-même n'est pas supprimé : il peut être propriétaire d'une
 * organisation partagée avec d'autres, dont les données ne sont pas les
 * siennes. Supprimer un compte est une opération distincte, qui devra traiter
 * ce cas.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ErasePersonalDataHandler
{
    /** @param iterable<PersonalDataContributor> $contributors */
    public function __construct(
        #[AutowireIterator('app.personal_data_contributor')]
        private iterable $contributors,
    ) {
    }

    public function __invoke(ErasePersonalData $command): void
    {
        foreach ($this->contributors as $contributor) {
            $contributor->erase();
        }
    }
}
