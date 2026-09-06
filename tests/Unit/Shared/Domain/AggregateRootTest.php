<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateRoot::class)]
final class AggregateRootTest extends TestCase
{
    public function testANewAggregateHasNoPendingEvent(): void
    {
        self::assertSame([], (new FakeAggregate())->releaseEvents());
    }

    public function testItKeepsRecordedEventsInOrder(): void
    {
        $aggregate = new FakeAggregate();
        $aggregate->doSomething('premier');
        $aggregate->doSomething('second');

        $events = $aggregate->releaseEvents();

        self::assertCount(2, $events);
        self::assertInstanceOf(FakeThingHappened::class, $events[0]);
        self::assertInstanceOf(FakeThingHappened::class, $events[1]);
        self::assertSame('premier', $events[0]->what);
        self::assertSame('second', $events[1]->what);
    }

    public function testReleasingEventsEmptiesTheAggregate(): void
    {
        $aggregate = new FakeAggregate();
        $aggregate->doSomething('unique');

        $aggregate->releaseEvents();

        self::assertSame(
            [],
            $aggregate->releaseEvents(),
            'Sans purge, le même événement serait publié à chaque enregistrement de l\'agrégat.',
        );
    }
}

final class FakeAggregate extends AggregateRoot
{
    public function doSomething(string $what): void
    {
        $this->recordThat(new FakeThingHappened($what));
    }
}

final readonly class FakeThingHappened implements DomainEvent
{
    public function __construct(public string $what)
    {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-05 12:00:00');
    }
}
