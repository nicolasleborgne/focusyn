<?php

declare(strict_types=1);

namespace App\Routine\Application\Command\OpenRoutine;

final readonly class OpenRoutine
{
    public function __construct(
        public string $name = '',
        public string $cadence = 'daily',
    ) {
    }
}
