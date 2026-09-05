<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\TotpSecret;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TotpSecret::class)]
final class TotpSecretTest extends TestCase
{
    public function testItAcceptsABase32Secret(): void
    {
        self::assertSame('JBSWY3DPEHPK3PXP', TotpSecret::fromString('JBSWY3DPEHPK3PXP')->toString());
    }

    public function testItUppercasesAndTrims(): void
    {
        self::assertSame('JBSWY3DPEHPK3PXP', TotpSecret::fromString(' jbswy3dpehpk3pxp ')->toString());
    }

    #[DataProvider('invalidSecrets')]
    public function testItRefusesWhatCannotBeABase32Secret(string $candidate): void
    {
        $this->expectException(InvalidArgumentException::class);

        TotpSecret::fromString($candidate);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSecrets(): iterable
    {
        yield 'vide' => [''];
        yield 'trop court' => ['JBSW'];
        yield 'caractères hors base32' => ['JBSWY3DPEHPK3PX1'];
        yield 'ponctuation' => ['JBSWY3DP-EHPK3PXP'];
    }

    public function testItGroupsItselfForManualEntry(): void
    {
        self::assertSame(
            'JBSW Y3DP EHPK 3PXP',
            TotpSecret::fromString('JBSWY3DPEHPK3PXP')->grouped(),
            'Saisir 32 caractères d\'affilée sans repère est une source d\'erreurs.',
        );
    }
}
