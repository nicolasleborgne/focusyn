<?php

declare(strict_types=1);

namespace App\Task\Application\Command\RemoveTask;

final readonly class RemoveTask
{
    public function __construct(
        public string $taskListId,
        public string $taskId,
    ) {
    }
}
