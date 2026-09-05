<?php

declare(strict_types=1);

namespace App\Tests\Factory\Task;

use App\Shared\Domain\TenantId;
use App\Task\Domain\Model\TaskList;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Model\TaskListName;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<TaskList> */
final class TaskListFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return TaskList::class;
    }

    public function ownedBy(TenantId $tenant): static
    {
        return $this->with(['tenantId' => $tenant]);
    }

    public function named(string $name): static
    {
        return $this->with(['name' => TaskListName::fromString($name)]);
    }

    protected function defaults(): array
    {
        // `words()` retourne un tableau ou une chaîne selon son second
        // argument ; on fige le type plutôt que de le forcer.
        /** @var list<string> $words */
        $words = self::faker()->unique()->words(2);
        $name = ucfirst(implode(' ', $words));

        return [
            'id' => TaskListId::generate(),
            'tenantId' => TenantId::generate(),
            'name' => TaskListName::fromString($name),
            'openedAt' => DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(
            /** @param array{id: TaskListId, tenantId: TenantId, name: TaskListName, openedAt: DateTimeImmutable} $parameters */
            static fn (array $parameters): TaskList => TaskList::open(
                $parameters['id'],
                $parameters['tenantId'],
                $parameters['name'],
                $parameters['openedAt'],
            ),
        );
    }
}
