<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\Accent;
use App\Identity\Domain\Model\Density;
use App\Identity\Domain\Model\DisplayPreferences;
use App\Identity\Domain\Model\MarkOpacity;
use App\Identity\Domain\Model\ProseFont;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DisplayPreferences::class)]
final class DisplayPreferencesTest extends TestCase
{
    public function testTheDefaultsAreThoseOfTheDesign(): void
    {
        $defaults = DisplayPreferences::default();

        self::assertSame(Accent::Slate, $defaults->accent);
        self::assertSame(ProseFont::Serif, $defaults->proseFont);
        self::assertSame(Density::Comfortable, $defaults->density);
        self::assertSame(0.45, $defaults->markOpacity->toFloat());
        self::assertTrue($defaults->previewPane);
    }

    public function testEachSettingCanBeChangedOnItsOwn(): void
    {
        $preferences = DisplayPreferences::default()
            ->withAccent(Accent::Brick)
            ->withProseFont(ProseFont::Sans);

        self::assertSame(Accent::Brick, $preferences->accent);
        self::assertSame(ProseFont::Sans, $preferences->proseFont);
        // Les autres réglages ne bougent pas.
        self::assertSame(Density::Comfortable, $preferences->density);
    }

    public function testChangingAReglageLeavesTheOriginalUntouched(): void
    {
        $original = DisplayPreferences::default();

        $original->withAccent(Accent::Olive);

        self::assertSame(
            Accent::Slate,
            $original->accent,
            'Les préférences sont immuables : la modification retourne un nouvel objet.',
        );
    }

    public function testTwoIdenticalPreferencesAreEqual(): void
    {
        self::assertTrue(DisplayPreferences::default()->equals(DisplayPreferences::default()));
        self::assertFalse(DisplayPreferences::default()->equals(DisplayPreferences::default()->withAccent(Accent::Ink)));
    }

    #[DataProvider('offeredOpacities')]
    public function testTheMarkOpacityIsLimitedToTheOfferedSteps(float $value): void
    {
        self::assertSame($value, MarkOpacity::fromFloat($value)->toFloat());
    }

    /** @return iterable<string, array{float}> */
    public static function offeredOpacities(): iterable
    {
        yield 'invisibles' => [0.0];
        yield 'discrètes' => [0.25];
        yield 'lisibles' => [0.45];
        yield 'affirmées' => [0.8];
    }

    public function testAnOpacityOutsideTheStepsIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Un curseur libre donnerait des marques presque invisibles ou
        // envahissantes ; les quatre paliers de la maquette suffisent.
        MarkOpacity::fromFloat(0.6);
    }

    public function testItSurvivesARoundTripThroughItsStoredForm(): void
    {
        $preferences = DisplayPreferences::default()
            ->withAccent(Accent::Tobacco)
            ->withDensity(Density::Compact)
            ->withMarkOpacity(MarkOpacity::fromFloat(0.0))
            ->withPreviewPane(false);

        self::assertTrue($preferences->equals(DisplayPreferences::fromArray($preferences->toArray())));
    }

    public function testAnUnknownStoredValueFallsBackOnTheDefault(): void
    {
        // Un réglage retiré d'une version à l'autre ne doit pas empêcher un
        // compte de se connecter.
        $preferences = DisplayPreferences::fromArray(['accent' => 'fuchsia', 'density' => 'aérée']);

        self::assertSame(Accent::Slate, $preferences->accent);
        self::assertSame(Density::Comfortable, $preferences->density);
    }

    public function testEachAccentCarriesItsColour(): void
    {
        self::assertSame('#41586e', Accent::Slate->hex());
        self::assertSame('slate', Accent::Slate->value);
        self::assertCount(5, Accent::cases());
    }
}
