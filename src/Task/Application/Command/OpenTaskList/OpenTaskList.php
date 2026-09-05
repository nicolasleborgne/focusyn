<?php

declare(strict_types=1);

namespace App\Task\Application\Command\OpenTaskList;

final readonly class OpenTaskList
{
    public function __construct(
        public string $name,
    ) {
    }
}
