<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Query\MarkdownOutline;
use App\Notebook\Application\Query\NotebookQuery;
use App\Notebook\Domain\Model\NoteBody;
use App\Notebook\Domain\Model\NoteId;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ShowNoteController extends AbstractController
{
    public function __construct(
        private readonly NotebookQuery $notebook,
        private readonly MarkdownOutline $outline,
    ) {
    }

    // Adresse identique dans les deux langues : un identifiant n'a pas de
    // traduction, et dupliquer la route n'apporterait rien.
    #[Route(
        path: '/notes/{id}',
        name: 'note_show',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function __invoke(string $id): Response
    {
        // Une note d'une autre organisation est invisible : le filtre la rend
        // introuvable, et on répond 404 sans révéler qu'elle existe.
        $note = $this->notebook->note(NoteId::fromString($id))
            ?? throw $this->createNotFoundException();

        return $this->render('notebook/note.html.twig', [
            'section' => ShellSection::Library,
            'note' => $note,
            'focusable' => true,
            'preview' => $this->outline->lines(NoteBody::fromString($note->body), withMarks: false),
        ]);
    }
}
