<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notebook\Domain;

use App\Notebook\Domain\Model\NoteBody;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoteBody::class)]
final class NoteBodyTest extends TestCase
{
    public function testItAcceptsAnEmptyBody(): void
    {
        self::assertSame('', NoteBody::empty()->toString());
        self::assertSame(0, NoteBody::empty()->wordCount());
    }

    public function testItCountsWordsWithoutCountingMarkdownMarks(): void
    {
        $body = NoteBody::fromString("# Titre\n\n- une puce\n- une autre\n");

        self::assertSame(
            5,
            $body->wordCount(),
            'Le prototype comptait « # » et « - » comme des mots ; un compteur de mots ne doit compter que des mots.',
        );
    }

    public function testItEstimatesAReadingTimeOfAtLeastOneMinute(): void
    {
        self::assertSame(1, NoteBody::fromString('trois petits mots')->readingMinutes());
        self::assertSame(2, NoteBody::fromString(implode(' ', array_fill(0, 300, 'mot')))->readingMinutes());
    }

    public function testItDrawsAnExcerptFromTheFirstProseLine(): void
    {
        $body = NoteBody::fromString("# Deux sommeils\n\n> Une citation\n\nAvant l'éclairage artificiel, la nuit se coupait en deux.");

        self::assertSame(
            "Avant l'éclairage artificiel, la nuit se coupait en deux.",
            $body->excerpt(),
            'Un extrait fait de titres et de marques markdown ne dirait rien du contenu.',
        );
    }

    public function testTheExcerptIsTruncatedOnAWordBoundary(): void
    {
        $body = NoteBody::fromString(implode(' ', array_fill(0, 60, 'mot')));

        $excerpt = $body->excerpt();

        self::assertLessThanOrEqual(160, mb_strlen($excerpt));
        self::assertStringEndsWith('…', $excerpt);
        self::assertStringNotContainsString('mo…', $excerpt);
    }

    public function testItFallsBackToTheFirstLineWhenThereIsNoProse(): void
    {
        // Une note faite de titres et de puces n'a aucune ligne de prose ;
        // laisser l'extrait vide donnerait une ligne muette dans les listes.
        $body = NoteBody::fromString("# Journal\n\n- Semaine 1-2 : réveil vers 3 h 10\n- Semaine 5 : réveil accepté");

        self::assertSame('Journal', $body->excerpt());
    }

    public function testAnEmptyBodyHasNoExcerpt(): void
    {
        self::assertSame('', NoteBody::empty()->excerpt());
    }

    public function testItRefusesABodyBeyondWhatANoteShouldHold(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NoteBody::fromString(str_repeat('a', 200_001));
    }

    public function testTwoIdenticalBodiesAreEqual(): void
    {
        self::assertTrue(NoteBody::fromString('# Titre')->equals(NoteBody::fromString('# Titre')));
        self::assertFalse(NoteBody::fromString('# Titre')->equals(NoteBody::fromString('# Autre')));
    }
}
