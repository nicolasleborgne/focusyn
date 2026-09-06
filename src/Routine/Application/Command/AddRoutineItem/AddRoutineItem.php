<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\AddRoutineItem;

final readonly class AddRoutineItem
{
    public function __construct(
        public string $routineId,
        public string $text,
    ) {
    }
}
