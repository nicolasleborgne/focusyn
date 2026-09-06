<?php

declare(strict_types=1);

namespace App\Routine\UI\Http;

use App\Routine\Application\Query\RoutineQuery;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ShowRoutineController extends AbstractController
{
    public function __construct(
        private readonly RoutineQuery $routines,
    ) {
    }

    #[Route(
        path: '/routines/{id}',
        name: 'routine_show',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function __invoke(string $id): Response
    {
        $routine = $this->routines->ofId($id) ?? throw $this->createNotFoundException();

        return $this->render('routine/routine.html.twig', [
            'section' => ShellSection::Tasks,
            'routine' => $routine,
            'focusable' => true,
        ]);
    }
}
