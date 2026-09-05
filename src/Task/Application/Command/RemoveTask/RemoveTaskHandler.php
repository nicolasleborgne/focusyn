<?php

declare(strict_types=1);

namespace App\Task\Application\Command\RemoveTask;

use App\Task\Application\Exception\TaskListNotFound;
use App\Task\Domain\Model\TaskItemId;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Repository\TaskListRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveTaskHandler
{
    public function __construct(
        private TaskListRepository $lists,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RemoveTask $command): void
    {
        $id = TaskListId::fromString($command->taskListId);
        $list = $this->lists->ofId($id) ?? throw TaskListNotFound::withId($id);

        $list->removeTask(TaskItemId::fromString($command->taskId), $this->clock->now());
        $this->lists->save($list);
    }
}
