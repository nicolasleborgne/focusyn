<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\SetNoteObsessions;

use App\Notebook\Application\Exception\NoteNotFound;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Repository\NoteRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SetNoteObsessionsHandler
{
    public function __construct(
        private NoteRepository $notes,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetNoteObsessions $command): void
    {
        $id = NoteId::fromString($command->noteId);
        $note = $this->notes->ofId($id) ?? throw NoteNotFound::withId($id);

        $wanted = array_map(ObsessionName::fromString(...), $command->obsessions);
        $now = $this->clock->now();

        foreach ($note->obsessions() as $current) {
            if (!self::contains($wanted, $current)) {
                $note->untag($current, $now);
            }
        }

        foreach ($wanted as $obsession) {
            $note->tagWith($obsession, $now);
        }

        $this->notes->save($note);
    }

    /** @param list<ObsessionName> $haystack */
    private static function contains(array $haystack, ObsessionName $needle): bool
    {
        foreach ($haystack as $candidate) {
            if ($candidate->equals($needle)) {
                return true;
            }
        }

        return false;
    }
}
