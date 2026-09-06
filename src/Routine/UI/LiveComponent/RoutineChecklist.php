<?php

declare(strict_types=1);

namespace App\Routine\UI\LiveComponent;

use App\Routine\Application\Command\AddRoutineItem\AddRoutineItem;
use App\Routine\Application\Command\RemoveRoutineItem\RemoveRoutineItem;
use App\Routine\Application\Command\ScheduleRoutineItem\ScheduleRoutineItem;
use App\Routine\Application\Command\TickRoutineItem\TickRoutineItem;
use App\Routine\Application\Query\RoutineItemView;
use App\Routine\Application\Query\RoutineQuery;
use App\Routine\Application\Query\RoutineView;
use App\Shared\Application\Command\CommandBus;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Les étapes d'une routine, à cocher et à cadencer.
 *
 * Comme la liste à cocher des tâches : chaque geste écrit en base, et la série
 * comme l'avancement se recalculent au serveur. Les reconstruire côté client
 * demanderait d'y refaire le calendrier — c'est-à-dire le domaine.
 */
#[AsLiveComponent(name: 'RoutineChecklist', template: 'components/RoutineChecklist.html.twig')]
final class RoutineChecklist
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $routineId = '';

    #[LiveProp(writable: true)]
    public string $draft = '';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly RoutineQuery $routines,
    ) {
    }

    public function routine(): ?RoutineView
    {
        return $this->routines->ofId($this->routineId);
    }

    #[LiveAction]
    public function tick(#[LiveArg] string $itemId): void
    {
        $this->commands->dispatch(new TickRoutineItem($this->routineId, $itemId));
    }

    #[LiveAction]
    public function remove(#[LiveArg] string $itemId): void
    {
        $this->commands->dispatch(new RemoveRoutineItem($this->routineId, $itemId));
    }

    #[LiveAction]
    public function add(): void
    {
        if ('' === trim($this->draft)) {
            return;
        }

        $this->commands->dispatch(new AddRoutineItem($this->routineId, $this->draft));
        $this->draft = '';
    }

    /**
     * Un jour se prend ou se rend d'un même clic.
     *
     * Retirer le dernier jour est refusé en silence : une étape hebdomadaire
     * sans aucun jour retomberait à « n'importe quel jour de la semaine », ce
     * qui n'est pas ce qu'on demande en décochant le dernier.
     */
    #[LiveAction]
    public function toggleDay(#[LiveArg] string $itemId, #[LiveArg] int $day): void
    {
        $item = $this->item($itemId);

        if (null === $item) {
            return;
        }

        $days = \in_array($day, $item->days, true)
            ? array_values(array_filter($item->days, static fn (int $kept): bool => $kept !== $day))
            : [...$item->days, $day];

        if ([] === $days) {
            return;
        }

        $this->commands->dispatch(new ScheduleRoutineItem($this->routineId, $itemId, $days, $item->rank));
    }

    #[LiveAction]
    public function setRank(#[LiveArg] string $itemId, #[LiveArg] int $rank): void
    {
        $item = $this->item($itemId);

        if (null === $item) {
            return;
        }

        $this->commands->dispatch(new ScheduleRoutineItem($this->routineId, $itemId, $item->days, $rank));
    }

    private function item(string $itemId): ?RoutineItemView
    {
        $routine = $this->routine();

        if (null === $routine) {
            return null;
        }

        foreach ($routine->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
