<?php

declare(strict_types=1);

namespace App\Task\Domain\Model;

use DateTimeImmutable;

/**
 * Une tâche à cocher. Entité interne à l'agrégat TaskList.
 */
final class TaskItem
{
    private bool $done = false;
    private ?DateTimeImmutable $completedAt = null;

    public function __construct(
        private readonly TaskList $list,
        private readonly TaskItemId $id,
        private readonly TaskText $text,
        private readonly int $position,
        private readonly DateTimeImmutable $addedAt,
    ) {
    }

    public function list(): TaskList
    {
        return $this->list;
    }

    public function id(): TaskItemId
    {
        return $this->id;
    }

    public function text(): TaskText
    {
        return $this->text;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function addedAt(): DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function toggle(DateTimeImmutable $at): void
    {
        $this->done = !$this->done;
        // Décocher efface la date : la conserver ferait mentir tout décompte
        // ultérieur de ce qui a été accompli, et quand.
        $this->completedAt = $this->done ? $at : null;
    }
}
