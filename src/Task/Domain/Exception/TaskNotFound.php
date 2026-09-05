<?php

declare(strict_types=1);

namespace App\Task\Domain\Exception;

use DomainException;

final class TaskNotFound extends DomainException
{
    public static function create(): self
    {
        return new self('Cette tâche ne fait pas partie de cette liste.');
    }
}
