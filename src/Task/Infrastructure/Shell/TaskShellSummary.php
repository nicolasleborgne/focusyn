<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Shell;

use App\Shared\Application\Home\TaskTeaser;
use App\Shared\Application\Shell\TaskListSummary;
use App\Shared\Application\Shell\TaskSummaryProvider;
use App\Task\Application\Query\TaskBoardQuery;
use App\Task\Application\Query\TaskListView;
use Psr\Clock\ClockInterface;

/**
 * Frontière du contexte Task : au-delà, plus personne ne manipule de
 * `TaskListView`.
 */
final readonly class TaskShellSummary implements TaskSummaryProvider
{
    public function __construct(
        private TaskBoardQuery $board,
        private ClockInterface $clock,
    ) {
    }

    public function openTaskCount(): int
    {
        return $this->board->openTaskCount();
    }

    /**
     * Ce qui a été coché aujourd'hui.
     *
     * Le jour civil, pas les vingt-quatre dernières heures : la revue du soir
     * regarde « ma journée », et une tâche cochée hier à 23 h n'en fait pas
     * partie.
     */
    public function completedToday(): int
    {
        $today = $this->clock->now()->format('Y-m-d');
        $done = 0;

        foreach ($this->board->board() as $list) {
            foreach ($list->completedItems as $item) {
                if ($item->completedAt?->format('Y-m-d') === $today) {
                    ++$done;
                }
            }
        }

        return $done;
    }

    public function lists(): array
    {
        return array_map(
            static fn (TaskListView $list): TaskListSummary => new TaskListSummary(
                $list->name,
                $list->id,
                \count($list->openItems),
            ),
            $this->board->board(),
        );
    }

    public function nextTasks(int $limit): array
    {
        $teasers = [];

        foreach ($this->board->board() as $list) {
            foreach ($list->openItems as $item) {
                $teasers[] = new TaskTeaser($item->id, $item->text, $list->name, $list->id);

                if (\count($teasers) === $limit) {
                    return $teasers;
                }
            }
        }

        return $teasers;
    }
}
