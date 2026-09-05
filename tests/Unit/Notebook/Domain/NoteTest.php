<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notebook\Domain;

use App\Notebook\Domain\Event\NoteWasWritten;
use App\Notebook\Domain\Model\AuthorId;
use App\Notebook\Domain\Model\Note;
use App\Notebook\Domain\Model\NoteBody;
use App\Notebook\Domain\Model\NoteId;
use App\Notebook\Domain\Model\NoteTitle;
use App\Notebook\Domain\Model\ObsessionName;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Note::class)]
final class NoteTest extends TestCase
{
    private const string WRITTEN_AT = '2026-09-05 10:00:00';
    private const string LATER = '2026-09-06 11:00:00';

    public function testWritingANoteAnnouncesIt(): void
    {
        $id = NoteId::generate();
        $tenant = TenantId::generate();

        $note = self::write($id, $tenant);

        $events = $note->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(NoteWasWritten::class, $events[0]);
        self::assertSame($id->toString(), $events[0]->noteId);
        self::assertSame($tenant->toString(), $events[0]->organizationId);
    }

    public function testANoteBelongsToTheOrganizationThatCreatedIt(): void
    {
        $tenant = TenantId::generate();

        $note = self::write(tenant: $tenant);

        self::assertTrue($tenant->equals($note->tenantId()));
    }

    public function testANewNoteCarriesItsTitleBodyAndObsessions(): void
    {
        $note = self::write();

        self::assertSame('Deux sommeils', $note->title()->toString());
        self::assertStringContainsString('éclairage artificiel', $note->body()->toString());
        self::assertSame(['Sommeil'], array_map(
            static fn (ObsessionName $name): string => $name->toString(),
            $note->obsessions(),
        ));
    }

    public function testRenamingUpdatesTheTitleAndTheTimestamp(): void
    {
        $note = self::write();

        $note->rename(NoteTitle::fromString('Deux sommeils, vraiment'), new DateTimeImmutable(self::LATER));

        self::assertSame('Deux sommeils, vraiment', $note->title()->toString());
        self::assertSame(self::LATER, $note->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testRenamingToTheSameTitleDoesNotTouchTheTimestamp(): void
    {
        $note = self::write();

        $note->rename(NoteTitle::fromString('  Deux sommeils  '), new DateTimeImmutable(self::LATER));

        self::assertSame(
            self::WRITTEN_AT,
            $note->updatedAt()->format('Y-m-d H:i:s'),
            'Une sauvegarde automatique qui ne change rien ne doit pas faire remonter la note dans les récentes.',
        );
    }

    public function testRewritingReplacesTheBody(): void
    {
        $note = self::write();

        $note->rewrite(NoteBody::fromString('# Autre chose'), new DateTimeImmutable(self::LATER));

        self::assertSame('# Autre chose', $note->body()->toString());
        self::assertSame(self::LATER, $note->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testRewritingWithAnIdenticalBodyChangesNothing(): void
    {
        $note = self::write();
        $body = $note->body()->toString();

        $note->rewrite(NoteBody::fromString($body), new DateTimeImmutable(self::LATER));

        self::assertSame(self::WRITTEN_AT, $note->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testAnObsessionCanBeAdded(): void
    {
        $note = self::write();

        $note->tagWith(ObsessionName::fromString('Histoire'), new DateTimeImmutable(self::LATER));

        self::assertSame(['Sommeil', 'Histoire'], array_map(
            static fn (ObsessionName $name): string => $name->toString(),
            $note->obsessions(),
        ));
    }

    public function testTaggingTwiceWithTheSameObsessionIsIdempotent(): void
    {
        $note = self::write();

        $note->tagWith(ObsessionName::fromString('sommeil'), new DateTimeImmutable(self::LATER));

        self::assertCount(
            1,
            $note->obsessions(),
            'Les obsessions sont comparées normalisées : « sommeil » et « Sommeil » sont la même.',
        );
    }

    public function testAnObsessionCanBeRemoved(): void
    {
        $note = self::write();
        $note->tagWith(ObsessionName::fromString('Histoire'), new DateTimeImmutable(self::LATER));

        $note->untag(ObsessionName::fromString('Sommeil'), new DateTimeImmutable(self::LATER));

        self::assertSame(['Histoire'], array_map(
            static fn (ObsessionName $name): string => $name->toString(),
            $note->obsessions(),
        ));
    }

    public function testItKnowsHowManyWordsItHolds(): void
    {
        $note = self::write();

        self::assertGreaterThan(5, $note->body()->wordCount());
    }

    /** @param list<ObsessionName>|null $obsessions */
    private static function write(
        ?NoteId $id = null,
        ?TenantId $tenant = null,
        ?array $obsessions = null,
    ): Note {
        return Note::write(
            $id ?? NoteId::generate(),
            $tenant ?? TenantId::generate(),
            AuthorId::generate(),
            NoteTitle::fromString('Deux sommeils'),
            NoteBody::fromString("# Deux sommeils\n\nAvant l'éclairage artificiel, la nuit se coupait en deux."),
            $obsessions ?? [ObsessionName::fromString('Sommeil')],
            new DateTimeImmutable(self::WRITTEN_AT),
        );
    }
}
