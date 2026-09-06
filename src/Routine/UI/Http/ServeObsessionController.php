<?php

declare(strict_types=1);

namespace App\Routine\UI\Http;

use App\Routine\Application\Command\ServeObsession\ServeObsession;
use App\Routine\Application\Exception\RoutineNotFound;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class ServeObsessionController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/routines/{id}/obsession', 'en' => '/routines/{id}/obsession'],
        name: 'routine_obsession',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('routine-obsession')]
    public function __invoke(string $id, Request $request): Response
    {
        try {
            $this->commands->dispatch(new ServeObsession($id, $request->request->getString('obsession')));
        } catch (RoutineNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('routine_show', ['id' => $id]);
    }
}
