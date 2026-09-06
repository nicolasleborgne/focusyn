<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\RemoveRoutineItem;

final readonly class RemoveRoutineItem
{
    public function __construct(
        public string $routineId,
        public string $itemId,
    ) {
    }
}
