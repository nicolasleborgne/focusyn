<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\DeleteRoutine;

final readonly class DeleteRoutine
{
    public function __construct(
        public string $routineId,
    ) {
    }
}
