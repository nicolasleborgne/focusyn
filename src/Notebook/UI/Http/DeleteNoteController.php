<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Command\DeleteNote\DeleteNote;
use App\Notebook\Application\Exception\NoteNotFound;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class DeleteNoteController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/notes/{id}/supprimer', 'en' => '/notes/{id}/delete'],
        name: 'note_delete',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('note-delete')]
    public function __invoke(string $id): Response
    {
        try {
            $this->commands->dispatch(new DeleteNote($id));
        } catch (NoteNotFound) {
            throw $this->createNotFoundException();
        }

        $this->addFlash('success', 'notebook.deleted');

        return $this->redirectToRoute('library');
    }
}
