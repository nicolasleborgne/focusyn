<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Model\HashedPassword;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HashedPassword::class)]
final class HashedPasswordTest extends TestCase
{
    public function testItCarriesAnAlreadyComputedFingerprint(): void
    {
        self::assertSame('$argon2id$v=19$…', HashedPassword::fromHash('$argon2id$v=19$…')->toString());
    }

    public function testItRefusesAnEmptyFingerprint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HashedPassword::fromHash('   ');
    }

    public function testItNeverRevealsItselfInADump(): void
    {
        ob_start();
        var_dump(HashedPassword::fromHash('$argon2id$secret'));
        $dumped = (string) ob_get_clean();

        self::assertStringNotContainsString(
            'secret',
            $dumped,
            'Une empreinte ne doit pas fuir dans un var_dump ni dans le profileur.',
        );
        self::assertStringContainsString('***', $dumped);
    }
}
