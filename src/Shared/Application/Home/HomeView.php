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
        /** Ce qu'il reste à cocher aujourd'hui, toutes routines confondues. */
        public int $routinesToTick,
        public array $dormantObsessions,
    ) {
    }

    public function hasNothingPending(): bool
    {
        return [] === $this->nextTasks;
    }
}
