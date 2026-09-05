<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Shell;

use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Shell\NotebookSummaryProvider;
use App\Shared\Application\Shell\ShellDataProvider;
use App\Shared\Application\Shell\ShellView;
use App\Shared\Application\Shell\TaskListSummary;

/**
 * Assemble ce que la coquille affiche, en interrogeant chaque contexte par son
 * port.
 *
 * Les listes de tâches restent celles de la maquette : le contexte Task n'existe
 * pas encore. Elles disparaîtront d'ici dès qu'il exposera son propre port —
 * aucun gabarit n'aura à changer.
 */
final readonly class AggregatedShellDataProvider implements ShellDataProvider
{
    public function __construct(
        private CurrentAccount $account,
        private NotebookSummaryProvider $notebook,
    ) {
    }

    public function forCurrentUser(): ShellView
    {
        return new ShellView(
            accountEmail: $this->account->emailOrNull() ?? '',
            noteCount: $this->notebook->noteCount(),
            obsessions: $this->notebook->obsessions(),
            taskLists: self::provisionalTaskLists(),
        );
    }

    /** @return list<TaskListSummary> */
    private static function provisionalTaskLists(): array
    {
        return [
            new TaskListSummary('Cette semaine', 'cette-semaine', 2),
            new TaskListSummary('Protocole sommeil', 'protocole-sommeil', 3),
            new TaskListSummary('Essais café', 'essais-cafe', 2),
        ];
    }
}
