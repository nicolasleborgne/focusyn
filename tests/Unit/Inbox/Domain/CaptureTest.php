<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbox\Domain;

use App\Inbox\Domain\Model\Capture;
use App\Inbox\Domain\Model\CaptureId;
use App\Inbox\Domain\Model\CaptureKind;
use App\Inbox\Domain\Model\CaptureSource;
use App\Inbox\Domain\Model\CaptureTitle;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ce qui tombe dans la boîte, et sous quelle forme.
 *
 * Une capture n'est pas une note : c'est de la matière brute, arrivée d'ailleurs
 * et pas encore triée. Elle ne connaît pas ce qu'elle deviendra.
 */
#[CoversClass(Capture::class)]
#[CoversClass(CaptureTitle::class)]
#[CoversClass(CaptureKind::class)]
final class CaptureTest extends TestCase
{
    public function testATextGivesItsFirstLineAsTitleAndKeepsTheRest(): void
    {
        $capture = $this->received("Le ratio n'est qu'une variable\n\nMouture, temps, agitation.");

        self::assertSame("Le ratio n'est qu'une variable", $capture->title()->toString());
        self::assertSame("Le ratio n'est qu'une variable\n\nMouture, temps, agitation.", $capture->body());
        self::assertSame(CaptureKind::Text, $capture->kind());
    }

    public function testAVeryLongFirstLineIsCutButNothingIsLost(): void
    {
        $long = str_repeat('a', 120);
        $capture = $this->received($long);

        // Le titre est une étiquette de liste, pas le contenu : on le coupe.
        self::assertSame(70, mb_strlen($capture->title()->toString()));
        // Le texte, lui, arrive entier — c'est la matière.
        self::assertSame($long, $capture->body());
    }

    #[DataProvider('links')]
    public function testAnAddressIsRecognisedAsALink(string $text): void
    {
        self::assertSame(CaptureKind::Link, $this->received($text)->kind());
    }

    /** @return iterable<string, array{string}> */
    public static function links(): iterable
    {
        yield 'https' => ['https://exemple.fr/ekirch'];
        yield 'http' => ['http://exemple.fr/ekirch'];
        yield 'entourée d\'espaces' => ["  https://exemple.fr/ekirch\n"];
        // Ce que dépose un partage entrant : le titre de la page, puis son
        // adresse. C'est un lien, même si la première ligne n'en est pas une.
        yield 'un partage entrant' => ["Segmented sleep in pre-industrial Europe\n\nhttps://exemple.fr/ekirch"];
    }

    #[DataProvider('notLinks')]
    public function testWhatOnlyMentionsAnAddressStaysText(string $text): void
    {
        self::assertSame(CaptureKind::Text, $this->received($text)->kind());
    }

    /** @return iterable<string, array{string}> */
    public static function notLinks(): iterable
    {
        // La nature se lit sur ce qui a été capturé en entier : une phrase qui
        // cite une adresse reste une phrase, et l'étiqueter « lien » ferait
        // attendre un article là où il y a une remarque.
        yield 'une phrase citant une adresse' => ['À lire : https://exemple.fr/ekirch, chapitre 8'];
        yield 'un protocole qui n\'en est pas un' => ['ftp://exemple.fr/archive'];
        yield 'du texte simple' => ['Relire Ekirch'];
    }

    public function testAnEmptyCaptureIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->received("   \n  ");
    }

    public function testACaptureKnowsWhereItCameFrom(): void
    {
        $capture = Capture::receive(
            CaptureId::generate(),
            TenantId::generate(),
            'https://exemple.fr/ekirch',
            CaptureSource::Shared,
            new DateTimeImmutable('2026-09-06 10:00'),
        );

        self::assertSame(CaptureSource::Shared, $capture->source());
        self::assertSame('2026-09-06 10:00', $capture->capturedAt()->format('Y-m-d H:i'));
    }

    private function received(string $text): Capture
    {
        return Capture::receive(
            CaptureId::generate(),
            TenantId::generate(),
            $text,
            CaptureSource::TypedIn,
            new DateTimeImmutable('2026-09-06 10:00'),
        );
    }
}
