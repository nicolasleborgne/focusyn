<?php

declare(strict_types=1);

namespace App\Task\Application\Command\RenameTaskList;

final readonly class RenameTaskList
{
    public function __construct(
        public string $taskListId,
        public string $name,
    ) {
    }
}
