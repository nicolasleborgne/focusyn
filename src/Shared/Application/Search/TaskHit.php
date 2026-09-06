<?php

declare(strict_types=1);

namespace App\Shared\Application\Search;

final readonly class TaskHit
{
    public function __construct(
        public string $listId,
        public string $listName,
        public string $text,
        public bool $done,
    ) {
    }
}
