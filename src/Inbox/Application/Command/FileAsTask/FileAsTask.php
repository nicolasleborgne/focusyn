<?php

declare(strict_types=1);

namespace App\Inbox\Application\Command\FileAsTask;

final readonly class FileAsTask
{
    public function __construct(
        public string $captureId,
        public string $taskListId,
    ) {
    }
}
