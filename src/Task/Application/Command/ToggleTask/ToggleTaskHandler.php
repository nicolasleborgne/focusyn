<?php

declare(strict_types=1);

namespace App\Task\Application\Command\ToggleTask;

use App\Task\Application\Exception\TaskListNotFound;
use App\Task\Domain\Model\TaskItemId;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Repository\TaskListRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ToggleTaskHandler
{
    public function __construct(
        private TaskListRepository $lists,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ToggleTask $command): void
    {
        $id = TaskListId::fromString($command->taskListId);
        $list = $this->lists->ofId($id) ?? throw TaskListNotFound::withId($id);

        $list->toggleTask(TaskItemId::fromString($command->taskId), $this->clock->now());
        $this->lists->save($list);
    }
}
