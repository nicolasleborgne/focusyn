<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\ChangeCadence;

final readonly class ChangeCadence
{
    public function __construct(
        public string $routineId,
        public string $cadence,
    ) {
    }
}
