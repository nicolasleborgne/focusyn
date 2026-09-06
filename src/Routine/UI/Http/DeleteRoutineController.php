<?php

declare(strict_types=1);

namespace App\Routine\UI\Http;

use App\Routine\Application\Command\DeleteRoutine\DeleteRoutine;
use App\Routine\Application\Exception\RoutineNotFound;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class DeleteRoutineController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/routines/{id}/supprimer', 'en' => '/routines/{id}/delete'],
        name: 'routine_delete',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('routine-delete')]
    public function __invoke(string $id): Response
    {
        try {
            $this->commands->dispatch(new DeleteRoutine($id));
        } catch (RoutineNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('tasks');
    }
}
