<?php

declare(strict_types=1);

namespace App\Task\Application\Query;

final readonly class TaskListView
{
    /**
     * @param list<TaskItemView> $openItems
     * @param list<TaskItemView> $completedItems
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $openItems,
        public array $completedItems,
        public int $progress,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->openItems && [] === $this->completedItems;
    }

    public function hasCompleted(): bool
    {
        return [] !== $this->completedItems;
    }
}
