<?php

declare(strict_types=1);

namespace App\Shared\Application\Home;

final readonly class TaskTeaser
{
    public function __construct(
        public string $text,
        public string $listName,
        public string $listSlug,
    ) {
    }
}
