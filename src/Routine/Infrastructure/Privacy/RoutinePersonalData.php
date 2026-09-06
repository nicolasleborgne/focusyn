<?php

declare(strict_types=1);

namespace App\Routine\Infrastructure\Privacy;

use App\Routine\Domain\Model\Routine;
use App\Routine\Domain\Model\RoutineItem;
use App\Routine\Domain\Model\RoutineTick;
use App\Routine\Domain\Repository\RoutineRepository;
use App\Shared\Application\Privacy\PersonalDataContributor;

/**
 * Les routines font partie des données du compte, cochages compris : ce sont
 * eux qui disent ce qu'on a tenu, et c'est bien la trace la plus personnelle
 * qu'une routine produise.
 */
final readonly class RoutinePersonalData implements PersonalDataContributor
{
    public function __construct(
        private RoutineRepository $routines,
    ) {
    }

    public function section(): string
    {
        return 'routines';
    }

    public function export(): array
    {
        return array_map(
            static fn (Routine $routine): array => [
                'nom' => $routine->name()->toString(),
                'cadence' => $routine->cadence()->value,
                'obsession' => $routine->obsession(),
                'ouverteLe' => $routine->openedAt()->format(\DATE_ATOM),
                'etapes' => array_map(
                    static fn (RoutineItem $item): array => [
                        'texte' => $item->text()->toString(),
                        'jours' => $item->days(),
                        'rang' => $item->rank(),
                    ],
                    $routine->items(),
                ),
                'coches' => array_map(
                    static fn (RoutineTick $tick): array => [
                        'etape' => $tick->itemId()->toString(),
                        'periode' => $tick->period(),
                        'cocheLe' => $tick->tickedAt()->format(\DATE_ATOM),
                    ],
                    $routine->ticks(),
                ),
            ],
            $this->routines->all(),
        );
    }

    public function erase(): void
    {
        foreach ($this->routines->all() as $routine) {
            $this->routines->remove($routine);
        }
    }
}
