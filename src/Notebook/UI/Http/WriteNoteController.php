<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Command\WriteNote\WriteNote;
use App\Notebook\Domain\Model\NoteId;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Crée une note vierge et ouvre l'éditeur dessus.
 *
 * Pas de formulaire préalable : la maquette crée puis laisse écrire. Un écran
 * demandant un titre avant d'avoir la moindre idée serait une friction.
 */
final class WriteNoteController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: ['fr' => '/notes/nouvelle', 'en' => '/notes/new'],
        name: 'note_write',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('note-write')]
    public function __invoke(): Response
    {
        $noteId = $this->commands->dispatch(new WriteNote(
            title: $this->translator->trans('notebook.untitled'),
        ));

        return $this->redirectToRoute('note_show', [
            'id' => $noteId instanceof NoteId ? $noteId->toString() : '',
        ]);
    }
}
