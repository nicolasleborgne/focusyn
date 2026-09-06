<?php

declare(strict_types=1);

namespace App\Shared\Application\Home;

final readonly class DormantObsession
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $weeksSinceLastNote,
    ) {
    }
}
