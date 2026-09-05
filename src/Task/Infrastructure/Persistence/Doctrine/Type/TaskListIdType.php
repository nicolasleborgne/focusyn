<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;
use App\Task\Domain\Model\TaskListId;

/** @extends EntityIdType<TaskListId> */
final class TaskListIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return TaskListId::class;
    }
}
