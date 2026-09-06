<?php

declare(strict_types=1);

namespace App\Routine\UI\Http;

use App\Routine\Application\Command\RenameRoutine\RenameRoutine;
use App\Routine\Application\Exception\RoutineNotFound;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class RenameRoutineController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/routines/{id}/nom', 'en' => '/routines/{id}/name'],
        name: 'routine_rename',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('routine-rename')]
    public function __invoke(string $id, Request $request): Response
    {
        try {
            $this->commands->dispatch(new RenameRoutine($id, $request->request->getString('name')));
        } catch (RoutineNotFound) {
            throw $this->createNotFoundException();
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'routine.name_required');
        }

        return $this->redirectToRoute('routine_show', ['id' => $id]);
    }
}
