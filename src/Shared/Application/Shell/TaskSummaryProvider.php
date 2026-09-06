<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

use App\Shared\Application\Home\TaskTeaser;

/**
 * Ce que la coquille et l'accueil ont besoin de savoir des tâches.
 *
 * Déclaré dans le noyau partagé, implémenté par Task : la barre latérale
 * affiche des listes sans avoir le droit de connaître ce qu'est une tâche.
 */
interface TaskSummaryProvider
{
    public function openTaskCount(): int;

    /** Combien de tâches ont été cochées aujourd'hui, toutes listes confondues. */
    public function completedToday(): int;

    /** @return list<TaskListSummary> */
    public function lists(): array;

    /** @return list<TaskTeaser> */
    public function nextTasks(int $limit): array;
}
