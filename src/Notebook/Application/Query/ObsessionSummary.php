<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

final readonly class ObsessionSummary
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $noteCount,
    ) {
    }
}
