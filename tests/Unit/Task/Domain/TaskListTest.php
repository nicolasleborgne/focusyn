<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Domain;

use App\Shared\Domain\TenantId;
use App\Task\Domain\Event\TaskListWasOpened;
use App\Task\Domain\Exception\TaskNotFound;
use App\Task\Domain\Model\TaskItemId;
use App\Task\Domain\Model\TaskList;
use App\Task\Domain\Model\TaskListId;
use App\Task\Domain\Model\TaskListName;
use App\Task\Domain\Model\TaskText;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskList::class)]
final class TaskListTest extends TestCase
{
    private const string OPENED_AT = '2026-09-05 10:00:00';
    private const string LATER = '2026-09-06 11:00:00';

    public function testOpeningAListAnnouncesIt(): void
    {
        $id = TaskListId::generate();
        $tenant = TenantId::generate();

        $list = self::open($id, $tenant);

        $events = $list->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TaskListWasOpened::class, $events[0]);
        self::assertSame($id->toString(), $events[0]->taskListId);
        self::assertSame($tenant->toString(), $events[0]->organizationId);
    }

    public function testANewListIsEmpty(): void
    {
        $list = self::open();

        self::assertSame([], $list->items());
        self::assertSame(0, $list->openCount());
        self::assertSame(0, $list->completedCount());
    }

    public function testATaskCanBeAdded(): void
    {
        $list = self::open();

        $list->addTask(TaskItemId::generate(), TaskText::fromString('Écrire la synthèse'), self::later());

        self::assertCount(1, $list->items());
        self::assertSame('Écrire la synthèse', $list->items()[0]->text()->toString());
        self::assertFalse($list->items()[0]->isDone());
    }

    public function testTasksKeepTheOrderTheyWereAddedIn(): void
    {
        $list = self::open();
        $list->addTask(TaskItemId::generate(), TaskText::fromString('Première'), self::later());
        $list->addTask(TaskItemId::generate(), TaskText::fromString('Deuxième'), self::later());
        $list->addTask(TaskItemId::generate(), TaskText::fromString('Troisième'), self::later());

        self::assertSame(['Première', 'Deuxième', 'Troisième'], array_map(
            static fn ($item): string => $item->text()->toString(),
            $list->items(),
        ));
    }

    public function testCheckingATaskMarksItDone(): void
    {
        $list = self::open();
        $taskId = TaskItemId::generate();
        $list->addTask($taskId, TaskText::fromString('Sortir le vélo'), self::later());

        $list->toggleTask($taskId, self::later());

        self::assertTrue($list->items()[0]->isDone());
        self::assertSame(self::LATER, $list->items()[0]->completedAt()?->format('Y-m-d H:i:s'));
    }

    public function testCheckingTwiceBringsTheTaskBack(): void
    {
        $list = self::open();
        $taskId = TaskItemId::generate();
        $list->addTask($taskId, TaskText::fromString('Sortir le vélo'), self::later());
        $list->toggleTask($taskId, self::later());

        $list->toggleTask($taskId, self::later());

        self::assertFalse($list->items()[0]->isDone());
        self::assertNull(
            $list->items()[0]->completedAt(),
            'Décocher doit effacer la date d\'achèvement, sinon elle mentirait au prochain rapport.',
        );
    }

    public function testTogglingAnUnknownTaskIsRefused(): void
    {
        $this->expectException(TaskNotFound::class);

        self::open()->toggleTask(TaskItemId::generate(), self::later());
    }

    public function testATaskCanBeRemoved(): void
    {
        $list = self::open();
        $taskId = TaskItemId::generate();
        $list->addTask($taskId, TaskText::fromString('À retirer'), self::later());
        $list->addTask(TaskItemId::generate(), TaskText::fromString('À garder'), self::later());

        $list->removeTask($taskId, self::later());

        self::assertCount(1, $list->items());
        self::assertSame('À garder', $list->items()[0]->text()->toString());
    }

    public function testItCountsWhatIsLeftToDo(): void
    {
        $list = self::open();
        $done = TaskItemId::generate();
        $list->addTask($done, TaskText::fromString('Faite'), self::later());
        $list->addTask(TaskItemId::generate(), TaskText::fromString('Ouverte'), self::later());
        $list->addTask(TaskItemId::generate(), TaskText::fromString('Ouverte aussi'), self::later());
        $list->toggleTask($done, self::later());

        self::assertSame(2, $list->openCount());
        self::assertSame(1, $list->completedCount());
        self::assertSame(33, $list->progress());
    }

    public function testAnEmptyListShowsNoProgressRatherThanADivisionByZero(): void
    {
        self::assertSame(0, self::open()->progress());
    }

    public function testAFullyCheckedListIsComplete(): void
    {
        $list = self::open();
        $taskId = TaskItemId::generate();
        $list->addTask($taskId, TaskText::fromString('Seule tâche'), self::later());
        $list->toggleTask($taskId, self::later());

        self::assertSame(100, $list->progress());
    }

    public function testRenamingChangesTheName(): void
    {
        $list = self::open();

        $list->rename(TaskListName::fromString('Protocole sommeil'), self::later());

        self::assertSame('Protocole sommeil', $list->name()->toString());
        self::assertSame(self::LATER, $list->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testRenamingToTheSameNameLeavesTheTimestampAlone(): void
    {
        $list = self::open();

        $list->rename(TaskListName::fromString('Cette semaine'), self::later());

        self::assertSame(self::OPENED_AT, $list->updatedAt()->format('Y-m-d H:i:s'));
    }

    private static function open(?TaskListId $id = null, ?TenantId $tenant = null): TaskList
    {
        return TaskList::open(
            $id ?? TaskListId::generate(),
            $tenant ?? TenantId::generate(),
            TaskListName::fromString('Cette semaine'),
            new DateTimeImmutable(self::OPENED_AT),
        );
    }

    private static function later(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::LATER);
    }
}
