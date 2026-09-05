<?php

declare(strict_types=1);

namespace App\Tests\Integration\Task;

use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Persistence\Doctrine\Filter\TenantFilter;
use App\Task\Domain\Model\TaskItemId;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Model\TaskText;
use App\Task\Domain\Repository\TaskListRepository;
use App\Tests\Factory\Task\TaskListFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineTaskListRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private TenantId $alice;
    private TenantId $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = TenantId::generate();
        $this->bob = TenantId::generate();
    }

    public function testTasksAreStoredWithTheirListAndKeepTheirOrder(): void
    {
        $list = TaskListFactory::new()->ownedBy($this->alice)->create();
        $list->addTask(TaskItemId::generate(), TaskText::fromString('Première'), new DateTimeImmutable());
        $list->addTask(TaskItemId::generate(), TaskText::fromString('Deuxième'), new DateTimeImmutable());
        $this->workingIn($this->alice);
        $this->lists()->save($list);
        $this->clear();

        $restored = $this->lists()->ofId($list->id());

        self::assertNotNull($restored);
        self::assertSame(['Première', 'Deuxième'], array_map(
            static fn ($item): string => $item->text()->toString(),
            $restored->items(),
        ));
    }

    public function testACheckedTaskStaysCheckedAcrossReloads(): void
    {
        $list = TaskListFactory::new()->ownedBy($this->alice)->create();
        $taskId = TaskItemId::generate();
        $list->addTask($taskId, TaskText::fromString('Sortir le vélo'), new DateTimeImmutable());
        $list->toggleTask($taskId, new DateTimeImmutable('2026-09-06 09:00:00'));
        $this->workingIn($this->alice);
        $this->lists()->save($list);
        $this->clear();

        $restored = $this->lists()->ofId($list->id());

        self::assertNotNull($restored);
        self::assertTrue($restored->items()[0]->isDone());
        self::assertSame('2026-09-06 09:00:00', $restored->items()[0]->completedAt()?->format('Y-m-d H:i:s'));
    }

    public function testRemovingATaskDeletesTheRow(): void
    {
        $list = TaskListFactory::new()->ownedBy($this->alice)->create();
        $taskId = TaskItemId::generate();
        $list->addTask($taskId, TaskText::fromString('À retirer'), new DateTimeImmutable());
        $this->workingIn($this->alice);
        $this->lists()->save($list);

        $list->removeTask($taskId, new DateTimeImmutable());
        $this->lists()->save($list);
        $this->clear();

        self::assertSame(0, $this->countRows('task_items'));
    }

    public function testAListOfAnotherOrganizationIsInvisible(): void
    {
        $foreign = TaskListFactory::new()->ownedBy($this->bob)->named('Secret')->create();
        TaskListFactory::new()->ownedBy($this->alice)->named('La mienne')->create();
        $this->workingIn($this->alice);

        self::assertNull($this->lists()->ofId($foreign->id()));
        self::assertCount(1, $this->lists()->all());
        self::assertSame('La mienne', $this->lists()->all()[0]->name()->toString());
    }

    public function testTheOpenTaskCounterNeverCrossesTheBoundary(): void
    {
        $mine = TaskListFactory::new()->ownedBy($this->alice)->create();
        $mine->addTask(TaskItemId::generate(), TaskText::fromString('La mienne'), new DateTimeImmutable());
        $theirs = TaskListFactory::new()->ownedBy($this->bob)->create();
        $theirs->addTask(TaskItemId::generate(), TaskText::fromString('La leur'), new DateTimeImmutable());
        $theirs->addTask(TaskItemId::generate(), TaskText::fromString('La leur aussi'), new DateTimeImmutable());

        $this->workingIn($this->alice);
        $this->lists()->save($mine);
        $this->lists()->save($theirs);
        $this->clear();

        $this->workingIn($this->alice);
        self::assertSame(1, $this->lists()->openTaskCount());

        $this->workingIn($this->bob);
        self::assertSame(2, $this->lists()->openTaskCount());
    }

    public function testDeletingAListTakesItsTasksWithIt(): void
    {
        $list = TaskListFactory::new()->ownedBy($this->alice)->create();
        $list->addTask(TaskItemId::generate(), TaskText::fromString('Emportée'), new DateTimeImmutable());
        $this->workingIn($this->alice);
        $this->lists()->save($list);

        $this->lists()->remove($list);
        $this->clear();

        self::assertSame(0, $this->countRows('task_items'));
        self::assertNull($this->lists()->ofId($list->id()));
    }

    public function testAnUnknownListYieldsNothing(): void
    {
        $this->workingIn($this->alice);

        self::assertNull($this->lists()->ofId(TaskListId::generate()));
    }

    /**
     * Arme le cloisonnement sans vider le gestionnaire : détacher une entité
     * déjà enregistrée la ferait ré-insérer au prochain `persist()`.
     */
    private function workingIn(TenantId $tenant): void
    {
        $filters = $this->entityManager()->getFilters();
        $filter = $filters->isEnabled('tenant') ? $filters->getFilter('tenant') : $filters->enable('tenant');
        $filter->setParameter(TenantFilter::PARAMETER, $tenant->toString());
    }

    private function lists(): TaskListRepository
    {
        $repository = self::getContainer()->get(TaskListRepository::class);
        self::assertInstanceOf(TaskListRepository::class, $repository);

        return $repository;
    }

    private function countRows(string $table): int
    {
        return (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    private function clear(): void
    {
        $this->entityManager()->clear();
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
