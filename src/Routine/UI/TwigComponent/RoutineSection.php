<?php

declare(strict_types=1);

namespace App\Routine\UI\TwigComponent;

use App\Routine\Application\Query\RoutineQuery;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Les routines sur l'écran des tâches, et sur l'accueil.
 *
 * Un composant plutôt qu'un bloc rendu par le contrôleur du tableau : celui-ci
 * appartient à Task, qui n'a pas le droit de connaître Routine.
 *
 * `only` distingue les deux usages — la liste entière sur l'écran des tâches,
 * ce qui reste à faire aujourd'hui sur l'accueil.
 */
#[AsTwigComponent(name: 'RoutineSection', template: 'components/RoutineSection.html.twig')]
final class RoutineSection
{
    /** `all` ou `due` */
    public string $only = 'all';

    public function __construct(
        private readonly RoutineQuery $routines,
    ) {
    }

    /** @return list<\App\Routine\Application\Query\RoutineView> */
    public function routines(): array
    {
        return 'due' === $this->only ? $this->routines->dueToday() : $this->routines->all();
    }
}
