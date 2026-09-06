<?php

declare(strict_types=1);

namespace App\Routine\Application\Query;

final readonly class RoutineView
{
    /**
     * @param list<RoutineItemView> $items    toutes les lignes, dans l'ordre
     * @param list<RoutineItemView> $dueToday celles attendues aujourd'hui
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $cadence,
        public ?string $obsession,
        public ?string $obsessionSlug,
        public array $items,
        public array $dueToday,
        public int $remaining,
        public int $streak,
    ) {
    }

    /** Ce qui est fait aujourd'hui, sur ce qui était dû. */
    public function doneToday(): int
    {
        return \count($this->dueToday) - $this->remaining;
    }

    public function progress(): int
    {
        return [] === $this->dueToday ? 0 : (int) round($this->doneToday() / \count($this->dueToday) * 100);
    }

    public function isDone(): bool
    {
        return [] !== $this->dueToday && 0 === $this->remaining;
    }
}
