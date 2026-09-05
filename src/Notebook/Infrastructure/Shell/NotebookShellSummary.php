<?php

declare(strict_types=1);

namespace App\Notebook\Infrastructure\Shell;

use App\Notebook\Application\Query\NotebookQuery;
use App\Notebook\Application\Query\NoteSummary;
use App\Notebook\Application\Query\ObsessionSummary as NotebookObsession;
use App\Shared\Application\Home\NoteTeaser;
use App\Shared\Application\Shell\NotebookSummaryProvider;
use App\Shared\Application\Shell\ObsessionSummary;

/**
 * Traduit les lectures du carnet dans le vocabulaire de la coquille.
 *
 * C'est la frontière du contexte : au-delà, plus personne ne manipule de
 * `NoteSummary`.
 */
final readonly class NotebookShellSummary implements NotebookSummaryProvider
{
    public function __construct(
        private NotebookQuery $notebook,
    ) {
    }

    public function noteCount(): int
    {
        return $this->notebook->count();
    }

    public function obsessions(): array
    {
        return array_map(
            static fn (NotebookObsession $obsession): ObsessionSummary => new ObsessionSummary(
                $obsession->name,
                $obsession->slug,
                $obsession->noteCount,
            ),
            $this->notebook->obsessions(),
        );
    }

    public function recentNotes(int $limit): array
    {
        return array_map(
            static fn (NoteSummary $note): NoteTeaser => new NoteTeaser(
                $note->title,
                $note->excerpt,
                $note->obsessions,
                $note->updatedAt,
                $note->wordCount,
            ),
            $this->notebook->recent($limit),
        );
    }
}
