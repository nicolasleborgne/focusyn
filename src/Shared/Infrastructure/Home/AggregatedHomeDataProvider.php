<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Home;

use App\Shared\Application\Home\HomeDataProvider;
use App\Shared\Application\Home\HomeView;
use App\Shared\Application\Shell\NotebookSummaryProvider;
use App\Shared\Application\Shell\TaskSummaryProvider;

/**
 * Accueil : notes et tâches réelles, chacune fournie par son contexte.
 */
final readonly class AggregatedHomeDataProvider implements HomeDataProvider
{
    private const int RECENT_NOTES = 3;
    private const int NEXT_TASKS = 5;

    public function __construct(
        private NotebookSummaryProvider $notebook,
        private TaskSummaryProvider $tasks,
    ) {
    }

    public function forCurrentUser(): HomeView
    {
        $recent = $this->notebook->recentNotes(self::RECENT_NOTES);

        return new HomeView(
            recentNotes: $recent,
            nextTasks: $this->tasks->nextTasks(self::NEXT_TASKS),
            // Approximation assumée : le total des notes récemment touchées.
            // Un vrai compteur mensuel demandera un historique d'écriture.
            monthlyWordCount: array_sum(array_map(
                static fn ($note): int => $note->wordCount,
                $recent,
            )),
        );
    }
}
