<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notebook\Domain;

use App\Notebook\Domain\Model\NoteKeywords;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * De quoi deux notes peuvent se recouper.
 *
 * Rien d'intelligent ici : des mots assez rares pour vouloir dire quelque
 * chose. Un rapprochement qui se trompe coûte peu — c'est une suggestion, pas
 * un classement —, mais un rapprochement fondé sur « dans » ou « cette » ferait
 * douter de tous les autres.
 */
#[CoversClass(NoteKeywords::class)]
final class NoteKeywordsTest extends TestCase
{
    public function testShortAndCommonWordsAreLeftOut(): void
    {
        $keywords = NoteKeywords::of('Cette note parle dans le vide avec des mots');

        // « cette », « avec », « note », « mots » sont trop courants ; « vide »
        // trop court pour peser. Il ne reste que « parle » — un verbe banal,
        // mais le tri se fait sur la longueur et la liste, pas sur le sens.
        self::assertSame(['parle'], $keywords->toArray());
    }

    public function testAccentsAndCaseDoNotSeparateTheSameWord(): void
    {
        $first = NoteKeywords::of('La fermentation lactique');
        $second = NoteKeywords::of('FERMENTATION Lactique');

        self::assertSame(['fermentation', 'lactique'], $first->shared($second));
    }

    public function testPunctuationDoesNotStickToWords(): void
    {
        $first = NoteKeywords::of('extraction, mouture ; agitation.');
        $second = NoteKeywords::of('(extraction) — mouture !');

        self::assertSame(['extraction', 'mouture'], $first->shared($second));
    }

    public function testAWordRepeatedCountsOnce(): void
    {
        $keywords = NoteKeywords::of('sommeil sommeil sommeil segmenté');

        self::assertSame(['segmente', 'sommeil'], $keywords->toArray());
    }

    public function testTwoNotesWithNothingInCommonShareNothing(): void
    {
        $sleep = NoteKeywords::of('Le sommeil biphasique et la lumière artificielle');
        $coffee = NoteKeywords::of('Extraction, mouture, agitation');

        self::assertSame([], $sleep->shared($coffee));
    }
}
