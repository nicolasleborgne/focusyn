<?php

declare(strict_types=1);

namespace App\Task\Application\Command\RenameTaskList;

use App\Task\Application\Exception\TaskListNotFound;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Model\TaskListName;
use App\Task\Domain\Repository\TaskListRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RenameTaskListHandler
{
    public function __construct(
        private TaskListRepository $lists,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RenameTaskList $command): void
    {
        $id = TaskListId::fromString($command->taskListId);
        $list = $this->lists->ofId($id) ?? throw TaskListNotFound::withId($id);

        $list->rename(TaskListName::fromString($command->name), $this->clock->now());
        $this->lists->save($list);
    }
}
