<?php

declare(strict_types=1);

namespace App\Tests\Unit\Assistant\Infrastructure;

use App\Assistant\Application\Exception\KeyVaultUnavailable;
use App\Assistant\Domain\Model\SealedKey;
use App\Assistant\Infrastructure\Crypto\SodiumKeyVault;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SodiumKeyVault::class)]
final class SodiumKeyVaultTest extends TestCase
{
    private const string SECRET = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';
    private const string OTHER = 'ZmVkY2JhOTg3NjU0MzIxMGZlZGNiYTk4NzY1NDMyMTA=';

    public function testASealedKeyComesBackIdentical(): void
    {
        $vault = new SodiumKeyVault(self::SECRET);

        self::assertSame('sk-ant-api03-secret', $vault->unseal($vault->seal('sk-ant-api03-secret')));
    }

    public function testTheSameKeySealedTwiceLooksDifferent(): void
    {
        $vault = new SodiumKeyVault(self::SECRET);

        // Sans nonce tiré au sort, deux comptes utilisant la même clé auraient
        // le même chiffré en base — et cela se verrait.
        self::assertNotSame(
            $vault->seal('sk-ant-api03-secret')->cipher(),
            $vault->seal('sk-ant-api03-secret')->cipher(),
        );
    }

    public function testTheClearKeyNeverAppearsInWhatIsStored(): void
    {
        $sealed = (new SodiumKeyVault(self::SECRET))->seal('sk-ant-api03-secret');

        self::assertStringNotContainsString('sk-ant', base64_decode($sealed->cipher(), true) ?: '');
    }

    public function testAnotherSecretCannotUnsealIt(): void
    {
        $sealed = (new SodiumKeyVault(self::SECRET))->seal('sk-ant-api03-secret');

        $this->expectException(KeyVaultUnavailable::class);
        (new SodiumKeyVault(self::OTHER))->unseal($sealed);
    }

    public function testATamperedCipherIsRefusedRatherThanDecryptedIntoNonsense(): void
    {
        $vault = new SodiumKeyVault(self::SECRET);
        $sealed = $vault->seal('sk-ant-api03-secret');
        $raw = base64_decode($sealed->cipher(), true);
        self::assertIsString($raw);
        $raw[\strlen($raw) - 1] = 'x' === $raw[\strlen($raw) - 1] ? 'y' : 'x';

        $this->expectException(KeyVaultUnavailable::class);
        $vault->unseal(SealedKey::fromCipher(base64_encode($raw)));
    }

    public function testASecretOfTheWrongSizeIsRefusedAtStartup(): void
    {
        $this->expectExceptionMessage('ASSISTANT_SECRET doit être 32 octets en base64.');
        new SodiumKeyVault(base64_encode('trop court'));
    }

    public function testAnAbsentSecretIsRefusedAtStartup(): void
    {
        $this->expectException(KeyVaultUnavailable::class);
        new SodiumKeyVault('');
    }
}
