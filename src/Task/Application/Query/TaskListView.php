<?php

declare(strict_types=1);

namespace App\Task\Application\Query;

final readonly class TaskListView
{
    /**
     * @param list<TaskItemView> $items          toutes, dans l'ordre d'écriture
     * @param list<TaskItemView> $openItems
     * @param list<TaskItemView> $completedItems
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $items,
        public array $openItems,
        public array $completedItems,
        public int $progress,
    ) {
    }

    /**
     * Les trois premières tâches, faites ou non.
     *
     * La carte du tableau montre le début de la liste, pas ce qu'il en reste :
     * une tâche cochée y garde sa place, barrée. C'est ce qui permet de
     * reconnaître une liste d'un coup d'œil.
     *
     * @return list<TaskItemView>
     */
    public function peek(): array
    {
        return \array_slice($this->items, 0, 3);
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
