<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Assistant;

use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Repository\NoteRepository;
use App\Shared\Application\Notebook\NoteSource;
use InvalidArgumentException;

/**
 * Le titre puis le corps, séparés d'une ligne vide : la note telle qu'elle se
 * lit, et telle que la maquette l'envoyait au modèle.
 */
final readonly class StoredNoteSource implements NoteSource
{
    public function __construct(
        private NoteRepository $notes,
    ) {
    }

    public function textOf(string $noteId): ?string
    {
        try {
            $note = $this->notes->ofId(NoteId::fromString($noteId));
        } catch (InvalidArgumentException) {
            return null;
        }

        return null === $note ? null : '# '.$note->title()->toString()."\n\n".$note->body()->toString();
    }
}
