<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\ScheduleRoutineItem;

final readonly class ScheduleRoutineItem
{
    /** @param list<int> $days jours ISO, 1 lundi à 7 dimanche */
    public function __construct(
        public string $routineId,
        public string $itemId,
        public array $days,
        public ?int $rank = null,
    ) {
    }
}
