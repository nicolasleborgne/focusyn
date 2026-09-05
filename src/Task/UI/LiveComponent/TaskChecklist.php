<?php

declare(strict_types=1);

namespace App\Task\UI\LiveComponent;

use App\Shared\Application\Command\CommandBus;
use App\Task\Application\Command\AddTask\AddTask;
use App\Task\Application\Command\RemoveTask\RemoveTask;
use App\Task\Application\Command\ToggleTask\ToggleTask;
use App\Task\Application\Query\TaskBoardQuery;
use App\Task\Application\Query\TaskListView;
use App\Task\Domain\Model\TaskListId;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Liste à cocher.
 *
 * Terrain naturel d'un Live Component : cocher une case écrit en base. Le faire
 * en Stimulus supposerait de reconstruire côté client l'état, les compteurs et
 * la barre d'avancement — c'est-à-dire de dupliquer le domaine.
 */
#[AsLiveComponent(name: 'TaskChecklist', template: 'components/TaskChecklist.html.twig')]
final class TaskChecklist
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $listId = '';

    #[LiveProp(writable: true)]
    public string $draft = '';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly TaskBoardQuery $board,
    ) {
    }

    public function list(): ?TaskListView
    {
        return $this->board->list(TaskListId::fromString($this->listId));
    }

    #[LiveAction]
    public function toggle(#[LiveArg] string $taskId): void
    {
        $this->commands->dispatch(new ToggleTask($this->listId, $taskId));
    }

    #[LiveAction]
    public function remove(#[LiveArg] string $taskId): void
    {
        $this->commands->dispatch(new RemoveTask($this->listId, $taskId));
    }

    #[LiveAction]
    public function add(): void
    {
        if ('' === trim($this->draft)) {
            return;
        }

        $this->commands->dispatch(new AddTask($this->listId, $this->draft));
        $this->draft = '';
    }
}
