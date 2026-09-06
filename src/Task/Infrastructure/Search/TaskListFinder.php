<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Search;

use App\Shared\Application\Search\TaskFinder;
use App\Shared\Application\Search\TaskHit;
use App\Task\Domain\Repository\TaskListRepository;

final readonly class TaskListFinder implements TaskFinder
{
    public function __construct(
        private TaskListRepository $lists,
    ) {
    }

    public function matching(string $query, int $limit = 20): array
    {
        $needle = mb_strtolower(trim($query));

        if ('' === $needle) {
            return [];
        }

        $hits = [];

        foreach ($this->lists->all() as $list) {
            foreach ($list->items() as $item) {
                if (str_contains(mb_strtolower($item->text()->toString()), $needle)) {
                    $hits[] = new TaskHit(
                        $list->id()->toString(),
                        $list->name()->toString(),
                        $item->text()->toString(),
                        $item->isDone(),
                    );
                }

                if (\count($hits) >= $limit) {
                    return $hits;
                }
            }
        }

        return $hits;
    }
}
