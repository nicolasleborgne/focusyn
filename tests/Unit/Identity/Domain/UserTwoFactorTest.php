<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Exception\TwoFactorAlreadyEnabled;
use App\Identity\Domain\Exception\TwoFactorNotEnabled;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\HashedPassword;
use App\Identity\Domain\Model\TotpSecret;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTwoFactorTest extends TestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXPFOCUSYN2';

    public function testANewAccountHasNoSecondFactor(): void
    {
        $user = self::user();

        self::assertFalse($user->hasTwoFactorEnabled());
        self::assertNull($user->totpSecret());
        self::assertSame([], $user->backupCodes());
    }

    public function testEnablingTheSecondFactorStoresTheSecretAndTheCodes(): void
    {
        $user = self::user();

        $user->enableTwoFactor(TotpSecret::fromString(self::SECRET), ['empreinte-1', 'empreinte-2']);

        self::assertTrue($user->hasTwoFactorEnabled());
        self::assertSame(self::SECRET, $user->totpSecret()?->toString());
        self::assertSame(['empreinte-1', 'empreinte-2'], $user->backupCodes());
    }

    public function testItRefusesToEnableTwiceWithoutDisablingFirst(): void
    {
        $user = self::user();
        $user->enableTwoFactor(TotpSecret::fromString(self::SECRET), ['empreinte-1']);

        $this->expectException(TwoFactorAlreadyEnabled::class);

        $user->enableTwoFactor(TotpSecret::fromString(self::SECRET), ['empreinte-2']);
    }

    public function testDisablingForgetsEverything(): void
    {
        $user = self::user();
        $user->enableTwoFactor(TotpSecret::fromString(self::SECRET), ['empreinte-1']);

        $user->disableTwoFactor();

        self::assertFalse($user->hasTwoFactorEnabled());
        self::assertNull($user->totpSecret());
        self::assertSame(
            [],
            $user->backupCodes(),
            'Des codes de secours survivant à la désactivation rouvriraient une porte que l\'utilisateur croit fermée.',
        );
    }

    public function testDisablingWhatIsNotEnabledIsRefused(): void
    {
        $this->expectException(TwoFactorNotEnabled::class);

        self::user()->disableTwoFactor();
    }

    public function testAConsumedBackupCodeIsRemoved(): void
    {
        $user = self::user();
        $user->enableTwoFactor(TotpSecret::fromString(self::SECRET), ['empreinte-1', 'empreinte-2']);

        $user->revokeBackupCode('empreinte-1');

        self::assertSame(['empreinte-2'], $user->backupCodes());
    }

    public function testRevokingAnUnknownCodeChangesNothing(): void
    {
        $user = self::user();
        $user->enableTwoFactor(TotpSecret::fromString(self::SECRET), ['empreinte-1']);

        $user->revokeBackupCode('empreinte-inconnue');

        self::assertSame(['empreinte-1'], $user->backupCodes());
    }

    public function testCodesCanBeRegeneratedWhichInvalidatesTheOldOnes(): void
    {
        $user = self::user();
        $user->enableTwoFactor(TotpSecret::fromString(self::SECRET), ['ancienne-1', 'ancienne-2']);

        $user->replaceBackupCodes(['nouvelle-1', 'nouvelle-2', 'nouvelle-3']);

        self::assertSame(['nouvelle-1', 'nouvelle-2', 'nouvelle-3'], $user->backupCodes());
    }

    public function testCodesCannotBeRegeneratedWithoutASecondFactor(): void
    {
        $this->expectException(TwoFactorNotEnabled::class);

        self::user()->replaceBackupCodes(['nouvelle-1']);
    }

    private static function user(): User
    {
        return User::register(
            UserId::generate(),
            EmailAddress::fromString('nicolas@focusyn.fr'),
            HashedPassword::fromHash('empreinte'),
            new DateTimeImmutable('2026-09-05 10:00:00'),
        );
    }
}
