<?php

declare(strict_types=1);

namespace App\Task\Application\Command\AddTask;

final readonly class AddTask
{
    public function __construct(
        public string $taskListId,
        public string $text,
    ) {
    }
}
