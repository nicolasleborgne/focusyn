<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notebook\Domain;

use App\Notebook\Domain\Model\ObsessionName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObsessionName::class)]
final class ObsessionNameTest extends TestCase
{
    public function testItKeepsTheWrittenForm(): void
    {
        self::assertSame('Sommeil', ObsessionName::fromString('  Sommeil  ')->toString());
    }

    public function testItComparesWithoutRegardToCaseOrAccents(): void
    {
        self::assertTrue(ObsessionName::fromString('Mémoire')->equals(ObsessionName::fromString('memoire')));
        self::assertFalse(ObsessionName::fromString('Mémoire')->equals(ObsessionName::fromString('Lecture')));
    }

    public function testItExposesASlugForUrls(): void
    {
        self::assertSame('fermentation', ObsessionName::fromString('Fermentation')->slug());
        self::assertSame('a-lire-relire', ObsessionName::fromString('À lire / relire')->slug());
    }

    #[DataProvider('unusableNames')]
    public function testItRefusesWhatCannotName(string $candidate): void
    {
        $this->expectException(InvalidArgumentException::class);

        ObsessionName::fromString($candidate);
    }

    /** @return iterable<string, array{string}> */
    public static function unusableNames(): iterable
    {
        yield 'vide' => [''];
        yield 'espaces seuls' => ['   '];
        yield 'ponctuation seule' => ['///'];
        yield 'trop long' => [str_repeat('a', 61)];
    }
}
