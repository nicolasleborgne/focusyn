<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

final readonly class TaskListSummary
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $openCount,
    ) {
    }

    public function hasOpenTasks(): bool
    {
        return $this->openCount > 0;
    }
}
