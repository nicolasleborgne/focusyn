<?php

declare(strict_types=1);

namespace App\Task\Application\Query;

use DateTimeImmutable;

final readonly class TaskItemView
{
    public function __construct(
        public string $id,
        public string $text,
        public bool $done,
        public ?DateTimeImmutable $completedAt = null,
    ) {
    }
}
