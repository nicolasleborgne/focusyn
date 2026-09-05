<?php

declare(strict_types=1);

namespace App\Task\UI\Http;

use App\Shared\Application\Command\CommandBus;
use App\Task\Application\Command\OpenTaskList\OpenTaskList;
use App\Task\Domain\Model\TaskListId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OpenTaskListController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: ['fr' => '/taches/nouvelle', 'en' => '/tasks/new'],
        name: 'task_list_open',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('task-list-open')]
    public function __invoke(): Response
    {
        $listId = $this->commands->dispatch(new OpenTaskList(
            $this->translator->trans('task.untitled_list'),
        ));

        return $this->redirectToRoute('task_list_show', [
            'id' => $listId instanceof TaskListId ? $listId->toString() : '',
        ]);
    }
}
