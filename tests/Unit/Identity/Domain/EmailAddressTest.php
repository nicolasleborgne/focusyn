<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Exception\InvalidEmailAddress;
use App\Identity\Domain\Model\EmailAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddress::class)]
final class EmailAddressTest extends TestCase
{
    public function testItKeepsAValidAddress(): void
    {
        self::assertSame('nicolas@focusyn.fr', EmailAddress::fromString('nicolas@focusyn.fr')->toString());
    }

    public function testItNormalisesCaseAndSurroundingSpaces(): void
    {
        $email = EmailAddress::fromString('  Nicolas@Focusyn.FR  ');

        self::assertSame(
            'nicolas@focusyn.fr',
            $email->toString(),
            'Sans normalisation, deux comptes pourraient coexister pour la même adresse.',
        );
    }

    #[DataProvider('invalidAddresses')]
    public function testItRefusesWhatIsNotAnAddress(string $candidate): void
    {
        $this->expectException(InvalidEmailAddress::class);

        EmailAddress::fromString($candidate);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAddresses(): iterable
    {
        yield 'vide' => [''];
        yield 'espaces seuls' => ['   '];
        yield 'sans arobase' => ['nicolas.focusyn.fr'];
        yield 'sans domaine' => ['nicolas@'];
        yield 'deux arobases' => ['nicolas@@focusyn.fr'];
    }

    public function testTwoAddressesWrittenDifferemmentAreEqual(): void
    {
        self::assertTrue(
            EmailAddress::fromString('Nicolas@focusyn.fr')->equals(EmailAddress::fromString('nicolas@FOCUSYN.fr')),
        );
    }

    public function testDifferentAddressesAreNotEqual(): void
    {
        self::assertFalse(
            EmailAddress::fromString('nicolas@focusyn.fr')->equals(EmailAddress::fromString('autre@focusyn.fr')),
        );
    }

    public function testItExposesItsDomain(): void
    {
        self::assertSame('focusyn.fr', EmailAddress::fromString('nicolas@focusyn.fr')->domain());
    }
}
