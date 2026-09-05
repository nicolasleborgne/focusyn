<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Command\SetNoteObsessions\SetNoteObsessions;
use App\Notebook\Application\Exception\NoteNotFound;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Étiquetage d'une note.
 *
 * Saisie libre séparée par des virgules, comme on écrirait les sujets en marge
 * d'un carnet. Il n'y a rien à créer au préalable : une obsession existe dès
 * qu'une note la mentionne.
 */
final class SetNoteObsessionsController extends AbstractController
{
    #[Route(
        path: '/notes/{id}/obsessions',
        name: 'note_obsessions',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('note-obsessions')]
    public function __invoke(string $id, Request $request, CommandBus $commands): Response
    {
        $names = array_values(array_filter(
            array_map(trim(...), explode(',', $request->request->getString('obsessions'))),
            static fn (string $name): bool => '' !== $name,
        ));

        try {
            $commands->dispatch(new SetNoteObsessions($id, $names));
        } catch (NoteNotFound) {
            throw $this->createNotFoundException();
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('note_show', ['id' => $id]);
    }
}
