<?php

declare(strict_types=1);

namespace App\Task\Application\Command\DeleteTaskList;

use App\Task\Application\Exception\TaskListNotFound;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Repository\TaskListRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteTaskListHandler
{
    public function __construct(
        private TaskListRepository $lists,
    ) {
    }

    public function __invoke(DeleteTaskList $command): void
    {
        $id = TaskListId::fromString($command->taskListId);
        $list = $this->lists->ofId($id) ?? throw TaskListNotFound::withId($id);

        $this->lists->remove($list);
    }
}
