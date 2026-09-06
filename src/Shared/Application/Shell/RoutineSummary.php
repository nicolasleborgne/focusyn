<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

final readonly class RoutineSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public int $remaining,
    ) {
    }
}
