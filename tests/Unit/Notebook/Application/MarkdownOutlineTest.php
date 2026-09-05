<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notebook\Application;

use App\Notebook\Application\Query\MarkdownOutline;
use App\Notebook\Application\Query\ProseLine;
use App\Notebook\Domain\Model\NoteBody;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Le markdown n'est jamais converti en HTML « propre » : il est découpé en
 * lignes, chacune gardant ses marques. L'aperçu emploie exactement le même
 * découpage, marques masquées — c'est ce qui garantit que les deux colonnes
 * restent alignées.
 */
#[CoversClass(MarkdownOutline::class)]
final class MarkdownOutlineTest extends TestCase
{
    public function testItRecognisesEachKindOfLine(): void
    {
        $lines = self::outline("# Titre\n## Sous-titre\n### Détail\n> Citation\n- Une puce\n1. Un rang\n\n---\nDu texte.");

        self::assertSame(
            ['h1', 'h2', 'h3', 'quote', 'list', 'list', 'blank', 'rule', 'paragraph'],
            array_map(static fn (ProseLine $line): string => $line->kind, $lines),
        );
    }

    public function testEachLineKeepsItsMark(): void
    {
        $lines = self::outline("# Titre\n> Citation\n- Une puce");

        self::assertSame(['# ', '> ', '- '], array_map(static fn (ProseLine $l): string => $l->mark, $lines));
    }

    public function testTheTextIsSeparatedFromItsMark(): void
    {
        $lines = self::outline('## Ce que ça change');

        self::assertSame('Ce que ça change', $lines[0]->plainText());
    }

    public function testWithoutMarksAListKeepsABullet(): void
    {
        $lines = self::outline('- Une puce', withMarks: false);

        self::assertSame(
            '•  ',
            $lines[0]->mark,
            'Sans marque, une puce sans puce cesserait de se lire comme une liste.',
        );
    }

    public function testWithoutMarksATitleLosesItsHashes(): void
    {
        $lines = self::outline('# Titre', withMarks: false);

        self::assertSame('', $lines[0]->mark);
        self::assertSame('h1', $lines[0]->kind);
    }

    public function testItSplitsBoldItalicCodeAndLinks(): void
    {
        $lines = self::outline('Un **gras**, un *italique*, du `code` et un [lien](https://exemple.fr).');

        $styles = array_map(static fn ($segment): string => $segment->style, $lines[0]->segments);

        self::assertSame(['plain', 'strong', 'plain', 'emphasis', 'plain', 'code', 'plain', 'link', 'plain'], $styles);
    }

    public function testASegmentCarriesItsOwnMarks(): void
    {
        $lines = self::outline('Un **gras**.');

        $strong = $lines[0]->segments[1];
        self::assertSame('gras', $strong->text);
        self::assertSame('**', $strong->before);
        self::assertSame('**', $strong->after);
    }

    public function testWithoutMarksTheSegmentMarksDisappear(): void
    {
        $lines = self::outline('Un **gras**.', withMarks: false);

        $strong = $lines[0]->segments[1];
        self::assertSame('gras', $strong->text);
        self::assertSame('', $strong->before);
        self::assertSame('', $strong->after);
    }

    public function testALinkKeepsItsTargetInTheMark(): void
    {
        $lines = self::outline('[At Day\'s Close](https://exemple.fr/ekirch)');

        $link = $lines[0]->segments[0];
        self::assertSame("At Day's Close", $link->text);
        self::assertSame('https://exemple.fr/ekirch', $link->href);
        self::assertSame('](https://exemple.fr/ekirch)', $link->after);
    }

    public function testFencedCodeIsLeftUntouched(): void
    {
        $lines = self::outline("```\nconst x = **1**;\n```");

        self::assertSame(['code', 'code', 'code'], array_map(static fn (ProseLine $l): string => $l->kind, $lines));
        self::assertSame(
            'const x = **1**;',
            $lines[1]->plainText(),
            'À l\'intérieur d\'un bloc de code, les astérisques sont du code, pas du gras.',
        );
    }

    public function testAnEmptyBodyOutlinesToNothing(): void
    {
        self::assertSame([], self::outline(''));
    }

    /** @return list<ProseLine> */
    private static function outline(string $markdown, bool $withMarks = true): array
    {
        return new MarkdownOutline()->lines(NoteBody::fromString($markdown), $withMarks);
    }
}
