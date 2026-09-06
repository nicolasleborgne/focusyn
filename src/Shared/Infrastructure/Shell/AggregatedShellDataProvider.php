<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Shell;

use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Shell\InboxSummaryProvider;
use App\Shared\Application\Shell\NotebookSummaryProvider;
use App\Shared\Application\Shell\RoutineSummaryProvider;
use App\Shared\Application\Shell\ShellDataProvider;
use App\Shared\Application\Shell\ShellView;
use App\Shared\Application\Shell\TaskSummaryProvider;

/**
 * Assemble ce que la coquille affiche, en interrogeant chaque contexte par son
 * port. Plus aucune donnée n'est factice.
 */
final readonly class AggregatedShellDataProvider implements ShellDataProvider
{
    public function __construct(
        private CurrentAccount $account,
        private NotebookSummaryProvider $notebook,
        private TaskSummaryProvider $tasks,
        private RoutineSummaryProvider $routines,
        private InboxSummaryProvider $inbox,
    ) {
    }

    public function forCurrentUser(): ShellView
    {
        return new ShellView(
            accountEmail: $this->account->emailOrNull() ?? '',
            noteCount: $this->notebook->noteCount(),
            obsessions: $this->notebook->obsessions(),
            taskLists: $this->tasks->lists(),
            routines: $this->routines->routines(),
            inboxCount: $this->inbox->pendingCount(),
        );
    }
}
