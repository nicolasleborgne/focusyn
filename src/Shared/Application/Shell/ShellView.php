<?php

declare(strict_types=1);

namespace App\Shared\Application\Shell;

/**
 * Tout ce que la coquille affiche autour du contenu d'un écran : compteurs de
 * navigation, obsessions suivies, listes de tâches et identité du compte.
 *
 * Assemblé une fois par requête et injecté dans les gabarits par une fonction
 * Twig, pour qu'aucun contrôleur n'ait à le transporter.
 */
final readonly class ShellView
{
    /**
     * @param list<ObsessionSummary> $obsessions
     * @param list<TaskListSummary>  $taskLists
     */
    public function __construct(
        public string $accountEmail,
        public int $noteCount,
        public array $obsessions,
        public array $taskLists,
    ) {
    }

    /**
     * Initiale affichée dans la pastille de compte.
     *
     * `mb_*` et non `strtoupper` : « éditeur@… » doit donner « É », pas un
     * octet tronqué.
     */
    public function accountInitial(): string
    {
        $email = trim($this->accountEmail);

        if ('' === $email) {
            return '?';
        }

        return mb_strtoupper(mb_substr($email, 0, 1));
    }

    public function openTaskCount(): int
    {
        return array_sum(array_map(
            static fn (TaskListSummary $list): int => $list->openCount,
            $this->taskLists,
        ));
    }

    public function obsessionCount(): int
    {
        return \count($this->obsessions);
    }
}
