<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Shell;

use App\Shared\Application\Home\TaskTeaser;
use App\Shared\Application\Shell\TaskListSummary;
use App\Shared\Application\Shell\TaskSummaryProvider;
use App\Task\Application\Query\TaskBoardQuery;
use App\Task\Application\Query\TaskListView;

/**
 * Frontière du contexte Task : au-delà, plus personne ne manipule de
 * `TaskListView`.
 */
final readonly class TaskShellSummary implements TaskSummaryProvider
{
    public function __construct(
        private TaskBoardQuery $board,
    ) {
    }

    public function openTaskCount(): int
    {
        return $this->board->openTaskCount();
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
                $teasers[] = new TaskTeaser($item->text, $list->name, $list->id);

                if (\count($teasers) === $limit) {
                    return $teasers;
                }
            }
        }

        return $teasers;
    }
}
