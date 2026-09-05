<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\RenameNote;

use App\Notebook\Application\Exception\NoteNotFound;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\NoteTitle;
use App\Notebook\Domain\Repository\NoteRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RenameNoteHandler
{
    public function __construct(
        private NoteRepository $notes,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RenameNote $command): void
    {
        $id = NoteId::fromString($command->noteId);
        $note = $this->notes->ofId($id) ?? throw NoteNotFound::withId($id);

        $note->rename(NoteTitle::fromString($command->title), $this->clock->now());
        $this->notes->save($note);
    }
}
