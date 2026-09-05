<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notebook\Domain;

use App\Notebook\Domain\Exception\TooManyObsessionPoints;
use App\Notebook\Domain\Model\Obsession;
use App\Notebook\Domain\Model\ObsessionBlurb;
use App\Notebook\Domain\Model\ObsessionId;
use App\Notebook\Domain\Model\ObsessionName;
use App\Notebook\Domain\Model\ObsessionPoint;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Une obsession existe dès qu'une note la mentionne ; cet agrégat n'est que sa
 * fiche éditoriale, facultative.
 */
#[CoversClass(Obsession::class)]
final class ObsessionTest extends TestCase
{
    private const string DESCRIBED_AT = '2026-09-05 10:00:00';
    private const string LATER = '2026-09-06 11:00:00';

    public function testItIsIdentifiedByTheSlugOfItsName(): void
    {
        $obsession = self::describe('À lire / relire');

        self::assertSame('a-lire-relire', $obsession->slug());
        self::assertSame('À lire / relire', $obsession->name()->toString());
    }

    public function testItBelongsToTheOrganizationThatWroteIt(): void
    {
        $tenant = TenantId::generate();

        self::assertTrue($tenant->equals(self::describe(tenant: $tenant)->tenantId()));
    }

    public function testANewRecordCarriesItsBlurbAndPoints(): void
    {
        $obsession = self::describe();

        self::assertSame('Segmentation, lumière, anxiété nocturne.', $obsession->blurb()?->toString());
        self::assertSame(
            ['La veille nocturne est un fait historique.', 'La lumière est la variable.'],
            array_map(static fn (ObsessionPoint $point): string => $point->toString(), $obsession->points()),
        );
    }

    public function testTheBlurbCanBeRewritten(): void
    {
        $obsession = self::describe();

        $obsession->rewriteBlurb(ObsessionBlurb::fromString('Autre chose.'), self::later());

        self::assertSame('Autre chose.', $obsession->blurb()?->toString());
        self::assertSame(self::LATER, $obsession->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testTheBlurbCanBeCleared(): void
    {
        $obsession = self::describe();

        $obsession->rewriteBlurb(null, self::later());

        self::assertNull($obsession->blurb());
    }

    public function testRewritingTheSameBlurbLeavesTheTimestampAlone(): void
    {
        $obsession = self::describe();

        $obsession->rewriteBlurb(ObsessionBlurb::fromString('Segmentation, lumière, anxiété nocturne.'), self::later());

        self::assertSame(self::DESCRIBED_AT, $obsession->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testThePointsCanBeReplacedAndKeepTheirOrder(): void
    {
        $obsession = self::describe();

        $obsession->replacePoints([
            ObsessionPoint::fromString('Premier'),
            ObsessionPoint::fromString('Deuxième'),
            ObsessionPoint::fromString('Troisième'),
        ], self::later());

        self::assertSame(
            ['Premier', 'Deuxième', 'Troisième'],
            array_map(static fn (ObsessionPoint $point): string => $point->toString(), $obsession->points()),
        );
    }

    public function testItRefusesMorePointsThanOneCanHoldInMind(): void
    {
        $obsession = self::describe();

        $this->expectException(TooManyObsessionPoints::class);

        $obsession->replacePoints(
            array_map(
                static fn (int $i): ObsessionPoint => ObsessionPoint::fromString('Point '.$i),
                range(1, 7),
            ),
            self::later(),
        );
    }

    public function testARecordWithoutBlurbNorPointsSaysNothing(): void
    {
        $bare = Obsession::describe(
            ObsessionId::generate(),
            TenantId::generate(),
            ObsessionName::fromString('Sommeil'),
            null,
            [],
            new DateTimeImmutable(self::DESCRIBED_AT),
        );

        self::assertFalse($bare->saysSomething());
        self::assertTrue(self::describe()->saysSomething());
    }

    public function testItCanBeRenamedWhenTheWrittenFormChanges(): void
    {
        $obsession = self::describe('Cafe');

        $obsession->rename(ObsessionName::fromString('Café'), self::later());

        self::assertSame('Café', $obsession->name()->toString());
        self::assertSame(
            'cafe',
            $obsession->slug(),
            'Le fragment d\'URL ne bouge pas : il identifie la fiche, et les liens déjà partagés doivent tenir.',
        );
    }

    public function testItRefusesARenameThatChangesItsIdentity(): void
    {
        $obsession = self::describe('Sommeil');

        $this->expectException(InvalidArgumentException::class);

        $obsession->rename(ObsessionName::fromString('Lecture'), self::later());
    }

    private static function describe(string $name = 'Sommeil', ?TenantId $tenant = null): Obsession
    {
        return Obsession::describe(
            ObsessionId::generate(),
            $tenant ?? TenantId::generate(),
            ObsessionName::fromString($name),
            ObsessionBlurb::fromString('Segmentation, lumière, anxiété nocturne.'),
            [
                ObsessionPoint::fromString('La veille nocturne est un fait historique.'),
                ObsessionPoint::fromString('La lumière est la variable.'),
            ],
            new DateTimeImmutable(self::DESCRIBED_AT),
        );
    }

    private static function later(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::LATER);
    }
}
