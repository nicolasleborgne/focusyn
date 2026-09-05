<?php

declare(strict_types=1);

namespace App\Task\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TenantScoped;
use App\Task\Domain\Event\TaskListWasOpened;
use App\Task\Domain\Exception\TaskNotFound;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Une liste de tâches à cocher.
 *
 * Les tâches vivent dans l'agrégat plutôt qu'à côté : leur ordre et leur
 * décompte n'ont de sens que rapportés à la liste, et rien ne les manipule
 * isolément.
 */
final class TaskList extends AggregateRoot implements TenantScoped
{
    /** @var Collection<int, TaskItem> */
    private Collection $items;

    private function __construct(
        private readonly TaskListId $id,
        private readonly TenantId $tenantId,
        private TaskListName $name,
        private readonly DateTimeImmutable $openedAt,
        private DateTimeImmutable $updatedAt,
    ) {
        $this->items = new ArrayCollection();
    }

    public static function open(
        TaskListId $id,
        TenantId $tenantId,
        TaskListName $name,
        DateTimeImmutable $openedAt,
    ): self {
        $list = new self($id, $tenantId, $name, $openedAt, $openedAt);
        $list->recordThat(new TaskListWasOpened(
            $id->toString(),
            $tenantId->toString(),
            $name->toString(),
            $openedAt,
        ));

        return $list;
    }

    public function id(): TaskListId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): TaskListName
    {
        return $this->name;
    }

    public function openedAt(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<TaskItem> */
    public function items(): array
    {
        $items = array_values($this->items->toArray());

        usort($items, static fn (TaskItem $a, TaskItem $b): int => $a->position() <=> $b->position());

        return $items;
    }

    /** @return list<TaskItem> */
    public function openItems(): array
    {
        return array_values(array_filter($this->items(), static fn (TaskItem $item): bool => !$item->isDone()));
    }

    /** @return list<TaskItem> */
    public function completedItems(): array
    {
        return array_values(array_filter($this->items(), static fn (TaskItem $item): bool => $item->isDone()));
    }

    public function openCount(): int
    {
        return \count($this->openItems());
    }

    public function completedCount(): int
    {
        return \count($this->completedItems());
    }

    /** Avancement en pourcentage, arrondi à l'entier. */
    public function progress(): int
    {
        $total = $this->items->count();

        if (0 === $total) {
            return 0;
        }

        return (int) round(($this->completedCount() / $total) * 100);
    }

    public function rename(TaskListName $name, DateTimeImmutable $at): void
    {
        if ($this->name->equals($name)) {
            return;
        }

        $this->name = $name;
        $this->updatedAt = $at;
    }

    public function addTask(TaskItemId $id, TaskText $text, DateTimeImmutable $at): void
    {
        $this->items->add(new TaskItem($this, $id, $text, $this->nextPosition(), $at));
        $this->updatedAt = $at;
    }

    public function toggleTask(TaskItemId $id, DateTimeImmutable $at): void
    {
        $item = $this->find($id) ?? throw TaskNotFound::create();

        $item->toggle($at);
        $this->updatedAt = $at;
    }

    public function removeTask(TaskItemId $id, DateTimeImmutable $at): void
    {
        $item = $this->find($id) ?? throw TaskNotFound::create();

        $this->items->removeElement($item);
        $this->updatedAt = $at;
    }

    private function nextPosition(): int
    {
        $positions = array_map(static fn (TaskItem $item): int => $item->position(), $this->items());

        return [] === $positions ? 0 : max($positions) + 1;
    }

    private function find(TaskItemId $id): ?TaskItem
    {
        foreach ($this->items as $item) {
            if ($item->id()->equals($id)) {
                return $item;
            }
        }

        return null;
    }
}
