<?php

declare(strict_types=1);

namespace App\Task\Domain\Repository;

use App\Task\Domain\Model\TaskList;
use App\Task\Domain\Model\TaskListId;

/**
 * Comme pour les notes, aucune organisation en paramètre : le cloisonnement est
 * appliqué une fois par le filtre Doctrine.
 */
interface TaskListRepository
{
    public function save(TaskList $list): void;

    public function remove(TaskList $list): void;

    public function ofId(TaskListId $id): ?TaskList;

    /** @return list<TaskList> */
    public function all(): array;

    public function openTaskCount(): int;
}
