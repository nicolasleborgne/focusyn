<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Privacy;

use App\Shared\Application\Privacy\PersonalDataContributor;
use App\Task\Domain\Model\TaskItem;
use App\Task\Domain\Model\TaskList;
use App\Task\Domain\Repository\TaskListRepository;

final readonly class TaskPersonalData implements PersonalDataContributor
{
    public function __construct(
        private TaskListRepository $lists,
    ) {
    }

    public function section(): string
    {
        return 'taches';
    }

    public function export(): array
    {
        return array_map(
            static fn (TaskList $list): array => [
                'nom' => $list->name()->toString(),
                'ouverteLe' => $list->openedAt()->format(\DATE_ATOM),
                'taches' => array_map(
                    static fn (TaskItem $item): array => [
                        'texte' => $item->text()->toString(),
                        'faite' => $item->isDone(),
                        'faiteLe' => $item->completedAt()?->format(\DATE_ATOM),
                    ],
                    $list->items(),
                ),
            ],
            $this->lists->all(),
        );
    }

    public function erase(): void
    {
        foreach ($this->lists->all() as $list) {
            $this->lists->remove($list);
        }
    }
}
