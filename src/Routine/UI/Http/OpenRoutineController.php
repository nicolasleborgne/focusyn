<?php

declare(strict_types=1);

namespace App\Routine\UI\Http;

use App\Routine\Application\Command\OpenRoutine\OpenRoutine;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class OpenRoutineController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/routines/nouvelle', 'en' => '/routines/new'],
        name: 'routine_open',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('routine-open')]
    public function __invoke(): Response
    {
        $id = $this->commands->dispatch(new OpenRoutine());

        return $this->redirectToRoute('routine_show', ['id' => (string) $id]);
    }
}
