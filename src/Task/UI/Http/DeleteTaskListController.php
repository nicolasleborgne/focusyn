<?php

declare(strict_types=1);

namespace App\Task\UI\Http;

use App\Shared\Application\Command\CommandBus;
use App\Task\Application\Command\DeleteTaskList\DeleteTaskList;
use App\Task\Application\Exception\TaskListNotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class DeleteTaskListController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/taches/{id}/supprimer', 'en' => '/tasks/{id}/delete'],
        name: 'task_list_delete',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('task-list-delete')]
    public function __invoke(string $id): Response
    {
        try {
            $this->commands->dispatch(new DeleteTaskList($id));
        } catch (TaskListNotFound) {
            throw $this->createNotFoundException();
        }

        $this->addFlash('success', 'task.list_deleted');

        return $this->redirectToRoute('tasks');
    }
}
