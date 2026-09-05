<?php

declare(strict_types=1);

namespace App\Task\Application\Command\ToggleTask;

final readonly class ToggleTask
{
    public function __construct(
        public string $taskListId,
        public string $taskId,
    ) {
    }
}
