<?php

declare(strict_types=1);

namespace App\Routine\UI\Http;

use App\Routine\Application\Command\ChangeCadence\ChangeCadence;
use App\Routine\Application\Exception\RoutineNotFound;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use ValueError;

/**
 * Changer de cadence oublie les calendriers et les cochages : ils étaient
 * rangés par période, et les périodes changent de nature. L'écran le dit avant
 * de le faire.
 */
final class ChangeCadenceController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/routines/{id}/cadence', 'en' => '/routines/{id}/cadence'],
        name: 'routine_cadence',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('routine-cadence')]
    public function __invoke(string $id, Request $request): Response
    {
        try {
            $this->commands->dispatch(new ChangeCadence($id, $request->request->getString('cadence')));
        } catch (RoutineNotFound) {
            throw $this->createNotFoundException();
        } catch (ValueError) {
            // Une cadence qui n'existe pas : rien à changer, rien à dire.
        }

        return $this->redirectToRoute('routine_show', ['id' => $id]);
    }
}
