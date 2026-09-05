<?php

declare(strict_types=1);

namespace App\Task\Application\Command\OpenTaskList;

use App\Shared\Application\Tenant\CurrentTenant;
use App\Task\Domain\Model\TaskList;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Model\TaskListName;
use App\Task\Domain\Repository\TaskListRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class OpenTaskListHandler
{
    public function __construct(
        private TaskListRepository $lists,
        private CurrentTenant $tenant,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenTaskList $command): TaskListId
    {
        $list = TaskList::open(
            TaskListId::generate(),
            $this->tenant->id(),
            TaskListName::fromString($command->name),
            $this->clock->now(),
        );

        $this->lists->save($list);

        return $list->id();
    }
}
