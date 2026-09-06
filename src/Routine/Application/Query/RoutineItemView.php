<?php

declare(strict_types=1);

namespace App\Routine\Application\Query;

final readonly class RoutineItemView
{
    /** @param list<int> $days jours ISO, 1 lundi à 7 dimanche */
    public function __construct(
        public string $id,
        public string $text,
        public bool $ticked,
        public bool $dueToday,
        public array $days,
        public ?int $rank,
    ) {
    }
}
