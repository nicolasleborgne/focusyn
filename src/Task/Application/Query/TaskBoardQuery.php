<?php

declare(strict_types=1);

namespace App\Task\Application\Query;

use App\Task\Domain\Model\TaskItem;
use App\Task\Domain\Model\TaskList;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Repository\TaskListRepository;

final readonly class TaskBoardQuery
{
    public function __construct(
        private TaskListRepository $lists,
    ) {
    }

    /** @return list<TaskListView> */
    public function board(): array
    {
        return array_map($this->view(...), $this->lists->all());
    }

    public function list(TaskListId $id): ?TaskListView
    {
        $list = $this->lists->ofId($id);

        return null === $list ? null : $this->view($list);
    }

    public function openTaskCount(): int
    {
        return $this->lists->openTaskCount();
    }

    private function view(TaskList $list): TaskListView
    {
        return new TaskListView(
            id: $list->id()->toString(),
            name: $list->name()->toString(),
            items: array_map($this->item(...), $list->items()),
            openItems: array_map($this->item(...), $list->openItems()),
            completedItems: array_map($this->item(...), $list->completedItems()),
            progress: $list->progress(),
        );
    }

    private function item(TaskItem $item): TaskItemView
    {
        return new TaskItemView(
            $item->id()->toString(),
            $item->text()->toString(),
            $item->isDone(),
            $item->completedAt(),
        );
    }
}
