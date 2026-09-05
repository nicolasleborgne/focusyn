<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Command\RenameNote\RenameNote;
use App\Notebook\Application\Exception\NoteNotFound;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class RenameNoteController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/notes/{id}/titre', 'en' => '/notes/{id}/title'],
        name: 'note_rename',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('note-rename')]
    public function __invoke(string $id, Request $request): Response
    {
        try {
            $this->commands->dispatch(new RenameNote($id, $request->request->getString('title')));
        } catch (NoteNotFound) {
            throw $this->createNotFoundException();
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'notebook.title_required');
        }

        return $this->redirectToRoute('note_show', ['id' => $id]);
    }
}
