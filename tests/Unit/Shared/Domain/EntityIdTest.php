<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\EntityId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityId::class)]
final class EntityIdTest extends TestCase
{
    public function testItGeneratesAValidIdentifier(): void
    {
        $id = FakeNoteId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->toString(),
            'Les identifiants sont des UUID v7 : ordonnés dans le temps, donc favorables aux index.',
        );
    }

    public function testGeneratedIdentifiersAreChronologicallySortable(): void
    {
        $first = FakeNoteId::generate();
        usleep(2000);
        $second = FakeNoteId::generate();

        self::assertLessThan($second->toString(), $first->toString());
    }

    public function testItRebuildsFromItsStringRepresentation(): void
    {
        $original = FakeNoteId::generate();

        $restored = FakeNoteId::fromString($original->toString());

        self::assertTrue($original->equals($restored));
    }

    public function testItRejectsAMalformedIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"pas-un-uuid" n\'est pas un identifiant valide');

        FakeNoteId::fromString('pas-un-uuid');
    }

    public function testTwoIdentifiersOfDifferentTypesAreNeverEqual(): void
    {
        $raw = FakeNoteId::generate()->toString();

        $note = FakeNoteId::fromString($raw);
        $task = FakeTaskId::fromString($raw);

        self::assertFalse(
            $note->equals($task),
            'Une même valeur portée par deux types ne doit pas être confondue : c\'est ce qui empêche de passer un identifiant de tâche là où une note est attendue.',
        );
    }

    public function testItIsUsableAsAString(): void
    {
        $id = FakeNoteId::generate();

        self::assertSame($id->toString(), (string) $id);
    }
}

final readonly class FakeNoteId extends EntityId
{
}

final readonly class FakeTaskId extends EntityId
{
}
