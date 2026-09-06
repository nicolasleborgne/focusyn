<?php

declare(strict_types=1);

namespace App\Routine\UI\LiveComponent;

use App\Routine\Application\Command\TickRoutineItem\TickRoutineItem;
use App\Routine\Application\Query\RoutineQuery;
use App\Routine\Application\Query\RoutineView;
use App\Shared\Application\Command\CommandBus;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Les routines du jour, sur l'accueil, **cochables sur place**.
 *
 * C'est ce que fait la maquette, et c'est ce qui donne son sens à la section :
 * une routine du matin se coche le matin, en ouvrant l'application — devoir
 * ouvrir chaque routine pour cela reviendrait à ne pas les montrer.
 *
 * Un Live Component, donc, et non le composant Twig d'avant : cocher écrit en
 * base, et la série comme le décompte se recalculent au serveur.
 */
#[AsLiveComponent(name: 'RoutineToday', template: 'components/RoutineToday.html.twig')]
final class RoutineToday
{
    use DefaultActionTrait;

    public function __construct(
        private readonly RoutineQuery $routines,
        private readonly CommandBus $commands,
    ) {
    }

    /**
     * Celles qui ont encore quelque chose à faire aujourd'hui.
     *
     * Une routine terminée disparaît de l'accueil : la garder pour montrer
     * qu'elle est faite occuperait la place de ce qui reste à faire.
     *
     * @return list<RoutineView>
     */
    public function due(): array
    {
        return $this->routines->dueToday();
    }

    public function remaining(): int
    {
        return $this->routines->remainingToday();
    }

    #[LiveAction]
    public function tick(#[LiveArg] string $routineId, #[LiveArg] string $itemId): void
    {
        $this->commands->dispatch(new TickRoutineItem($routineId, $itemId));
    }
}
