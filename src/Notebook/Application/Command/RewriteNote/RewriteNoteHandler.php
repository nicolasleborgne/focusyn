<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\RewriteNote;

use App\Notebook\Application\Exception\NoteNotFound;
use App\Notebook\Domain\Model\NoteBody;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Repository\NoteRepository;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RewriteNoteHandler
{
    public function __construct(
        private NoteRepository $notes,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RewriteNote $command): DateTimeImmutable
    {
        $id = NoteId::fromString($command->noteId);
        $note = $this->notes->ofId($id) ?? throw NoteNotFound::withId($id);

        $note->rewrite(NoteBody::fromString($command->body), $this->clock->now());
        $this->notes->save($note);

        return $note->updatedAt();
    }
}
