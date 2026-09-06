<?php

declare(strict_types=1);

namespace App\Routine\Application\Query;

use App\Routine\Domain\Model\Routine;
use App\Routine\Domain\Model\RoutineId;
use App\Routine\Domain\Model\RoutineItem;
use App\Routine\Domain\Repository\RoutineRepository;
use App\Shared\Application\Notebook\ObsessionDirectory;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Les routines telles qu'on les regarde aujourd'hui.
 *
 * « Aujourd'hui » est lu une seule fois par requête : deux appels à l'horloge
 * séparés par un changement de jour donneraient un écran incohérent, et cela
 * arrive une fois par an à minuit — assez pour ne pas s'y exposer.
 */
final class RoutineQuery
{
    public function __construct(
        private readonly RoutineRepository $routines,
        private readonly ObsessionDirectory $obsessions,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<RoutineView> */
    public function all(): array
    {
        $now = $this->clock->now();

        return array_map(fn (Routine $routine): RoutineView => $this->view($routine, $now), $this->routines->all());
    }

    public function ofId(string $id): ?RoutineView
    {
        $routine = $this->routines->ofId(RoutineId::fromString($id));

        return null === $routine ? null : $this->view($routine, $this->clock->now());
    }

    /** Ce qu'il reste à faire aujourd'hui, toutes routines confondues. */
    public function remainingToday(): int
    {
        $now = $this->clock->now();

        return array_sum(array_map(
            static fn (Routine $routine): int => $routine->remainingOn($now),
            $this->routines->all(),
        ));
    }

    /** @return list<RoutineView> celles qui ont encore quelque chose à faire */
    public function dueToday(): array
    {
        return array_values(array_filter($this->all(), static fn (RoutineView $view): bool => $view->remaining > 0));
    }

    private function view(Routine $routine, DateTimeImmutable $now): RoutineView
    {
        $due = $routine->dueOn($now);
        $dueIds = array_map(static fn (RoutineItem $item): string => $item->id()->toString(), $due);

        $items = array_map(
            static fn (RoutineItem $item): RoutineItemView => new RoutineItemView(
                id: $item->id()->toString(),
                text: $item->text()->toString(),
                ticked: $routine->isTicked($item->id(), $now),
                dueToday: \in_array($item->id()->toString(), $dueIds, true),
                days: $item->days(),
                rank: $item->rank(),
            ),
            $routine->items(),
        );

        return new RoutineView(
            id: $routine->id()->toString(),
            name: $routine->name()->toString(),
            cadence: $routine->cadence()->value,
            obsession: $routine->obsession(),
            obsessionSlug: null === $routine->obsession() ? null : $this->obsessions->slugOf($routine->obsession()),
            items: $items,
            dueToday: array_values(array_filter($items, static fn (RoutineItemView $item): bool => $item->dueToday)),
            remaining: $routine->remainingOn($now),
            streak: $routine->streakOn($now),
        );
    }
}
