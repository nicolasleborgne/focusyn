<?php

declare(strict_types=1);

namespace App\Shared\Application\Home;

/**
 * Ce que l'accueil montre : de quoi reprendre le fil, rien de plus.
 */
final readonly class HomeView
{
    /**
     * @param list<NoteTeaser>       $recentNotes
     * @param list<TaskTeaser>       $nextTasks
     * @param list<DormantObsession> $dormantObsessions
     */
    public function __construct(
        public array $recentNotes,
        public array $nextTasks,
        public int $monthlyWordCount,
        public array $dormantObsessions,
    ) {
    }

    public function hasNothingPending(): bool
    {
        return [] === $this->nextTasks;
    }
}
