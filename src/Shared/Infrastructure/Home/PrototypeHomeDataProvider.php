<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Home;

use App\Shared\Application\Home\HomeDataProvider;
use App\Shared\Application\Home\HomeView;
use App\Shared\Application\Home\TaskTeaser;
use App\Shared\Application\Shell\NotebookSummaryProvider;

/**
 * Accueil : les notes viennent désormais du carnet réel.
 *
 * Les prochaines tâches restent celles de la maquette, faute de contexte Task.
 * Elles sont marquées comme telles pour qu'on ne les prenne pas pour des
 * données vraies.
 */
final readonly class PrototypeHomeDataProvider implements HomeDataProvider
{
    public function __construct(
        private NotebookSummaryProvider $notebook,
    ) {
    }

    public function forCurrentUser(): HomeView
    {
        $recent = $this->notebook->recentNotes(3);

        return new HomeView(
            recentNotes: $recent,
            nextTasks: self::provisionalTasks(),
            monthlyWordCount: array_sum(array_map(
                static fn ($note): int => $note->wordCount,
                $recent,
            )),
        );
    }

    /** @return list<TaskTeaser> */
    private static function provisionalTasks(): array
    {
        return [
            new TaskTeaser('Écrire la synthèse Sommeil', 'Cette semaine', 'cette-semaine'),
            new TaskTeaser('Trier les 6 notes en friche', 'Cette semaine', 'cette-semaine'),
            new TaskTeaser('Deux semaines sans écran après 21 h', 'Protocole sommeil', 'protocole-sommeil'),
        ];
    }
}
