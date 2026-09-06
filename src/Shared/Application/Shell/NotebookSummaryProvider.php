<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

use App\Shared\Application\Home\NoteTeaser;

/**
 * Ce que la coquille et l'accueil ont besoin de savoir du carnet.
 *
 * Déclaré dans le noyau partagé, implémenté par Notebook : la coquille affiche
 * un compteur de notes sans avoir le droit de connaître ce qu'est une note.
 */
interface NotebookSummaryProvider
{
    /**
     * Les obsessions qu'on ne nourrit plus.
     *
     * @return list<\App\Shared\Application\Home\DormantObsession>
     */
    public function dormantObsessions(): array;

    public function noteCount(): int;

    /** @return list<ObsessionSummary> */
    public function obsessions(): array;

    /** @return list<NoteTeaser> */
    public function recentNotes(int $limit): array;

    public function wordsWrittenThisMonth(): int;
}
