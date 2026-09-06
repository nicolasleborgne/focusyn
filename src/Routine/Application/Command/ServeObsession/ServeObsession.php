<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\ServeObsession;

final readonly class ServeObsession
{
    public function __construct(
        public string $routineId,
        public ?string $obsession,
    ) {
    }
}
