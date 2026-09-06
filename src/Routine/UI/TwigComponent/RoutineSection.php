<?php

declare(strict_types=1);

namespace App\Routine\UI\TwigComponent;

use App\Routine\Application\Query\RoutineQuery;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Les routines sur l'écran des tâches, en regard des listes.
 *
 * Un composant plutôt qu'un bloc rendu par le contrôleur du tableau : celui-ci
 * appartient à Task, qui n'a pas le droit de connaître Routine.
 *
 * L'accueil, lui, a son propre composant : là-bas les étapes se cochent, donc
 * il y faut un Live Component, pas une liste.
 */
#[AsTwigComponent(name: 'RoutineSection', template: 'components/RoutineSection.html.twig')]
final class RoutineSection
{
    public function __construct(
        private readonly RoutineQuery $routines,
    ) {
    }

    /** @return list<\App\Routine\Application\Query\RoutineView> */
    public function routines(): array
    {
        return $this->routines->all();
    }
}
