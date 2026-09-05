<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Shell;

use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Shell\ObsessionSummary;
use App\Shared\Application\Shell\ShellDataProvider;
use App\Shared\Application\Shell\ShellView;
use App\Shared\Application\Shell\TaskListSummary;

/**
 * PROVISOIRE — sert les données de la maquette (project/Focusyn.dc.html).
 *
 * Elle existe pour que la coquille soit visible et jugeable avant que les
 * contextes Notebook, Task et Identity n'existent. À remplacer par une
 * implémentation qui agrège ces contextes ; aucun gabarit n'aura à changer,
 * seule la déclaration de service bougera.
 */
final readonly class PrototypeShellDataProvider implements ShellDataProvider
{
    public function __construct(
        private CurrentAccount $account,
    ) {
    }

    public function forCurrentUser(): ShellView
    {
        return new ShellView(
            // Seule donnée déjà réelle : le compte connecté. Le reste attend
            // les contextes Notebook et Task.
            accountEmail: $this->account->emailOrNull() ?? '',
            noteCount: 8,
            obsessions: [
                new ObsessionSummary('Sommeil', 'sommeil', 2),
                new ObsessionSummary('Café', 'cafe', 1),
                new ObsessionSummary('Typographie', 'typographie', 1),
                new ObsessionSummary('Mémoire', 'memoire', 2),
                new ObsessionSummary('Vélo', 'velo', 1),
                new ObsessionSummary('Fermentation', 'fermentation', 1),
                new ObsessionSummary('Histoire', 'histoire', 1),
                new ObsessionSummary('Lecture', 'lecture', 1),
            ],
            taskLists: [
                new TaskListSummary('Cette semaine', 'cette-semaine', 2),
                new TaskListSummary('Protocole sommeil', 'protocole-sommeil', 3),
                new TaskListSummary('Essais café', 'essais-cafe', 2),
                new TaskListSummary('Matériel à acheter', 'materiel-a-acheter', 2),
                new TaskListSummary('À lire / relire', 'a-lire-relire', 2),
            ],
        );
    }
}
