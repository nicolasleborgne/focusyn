<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\TickRoutineItem;

final readonly class TickRoutineItem
{
    public function __construct(
        public string $routineId,
        public string $itemId,
    ) {
    }
}
