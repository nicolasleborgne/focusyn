<?php

declare(strict_types=1);

namespace App\Task\UI\Http;

use App\Shared\Application\Command\CommandBus;
use App\Task\Application\Command\RenameTaskList\RenameTaskList;
use App\Task\Application\Exception\TaskListNotFound;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class RenameTaskListController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/taches/{id}/nom', 'en' => '/tasks/{id}/name'],
        name: 'task_list_rename',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('task-list-rename')]
    public function __invoke(string $id, Request $request): Response
    {
        try {
            $this->commands->dispatch(new RenameTaskList($id, $request->request->getString('name')));
        } catch (TaskListNotFound) {
            throw $this->createNotFoundException();
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'task.name_required');
        }

        return $this->redirectToRoute('task_list_show', ['id' => $id]);
    }
}
