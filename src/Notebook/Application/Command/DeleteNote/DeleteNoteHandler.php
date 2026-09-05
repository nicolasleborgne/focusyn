<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\DeleteNote;

use App\Notebook\Application\Exception\NoteNotFound;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Repository\NoteRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteNoteHandler
{
    public function __construct(
        private NoteRepository $notes,
    ) {
    }

    public function __invoke(DeleteNote $command): void
    {
        $id = NoteId::fromString($command->noteId);
        $note = $this->notes->ofId($id) ?? throw NoteNotFound::withId($id);

        $this->notes->remove($note);
    }
}
