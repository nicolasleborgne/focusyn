<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\EntityIdType;
use App\Task\Domain\Model\TaskItemId;

/** @extends EntityIdType<TaskItemId> */
final class TaskItemIdType extends EntityIdType
{
    protected function idClass(): string
    {
        return TaskItemId::class;
    }
}
