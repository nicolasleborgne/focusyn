<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\RenameRoutine;

final readonly class RenameRoutine
{
    public function __construct(
        public string $routineId,
        public string $name,
    ) {
    }
}
