<?php

declare(strict_types=1);

namespace App\Task\Application\Command\DeleteTaskList;

final readonly class DeleteTaskList
{
    public function __construct(
        public string $taskListId,
    ) {
    }
}
