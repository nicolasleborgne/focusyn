<?php

declare(strict_types=1);

namespace App\Task\Application\Exception;

use App\Task\Domain\Model\TaskListId;
use DomainException;

/**
 * Levée aussi bien quand la liste n'existe pas que lorsqu'elle appartient à une
 * autre organisation : distinguer les deux révélerait son existence.
 */
final class TaskListNotFound extends DomainException
{
    public static function withId(TaskListId $id): self
    {
        return new self(\sprintf('Aucune liste pour l\'identifiant %s.', $id->toString()));
    }
}
