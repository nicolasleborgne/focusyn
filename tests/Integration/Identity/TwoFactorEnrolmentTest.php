<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Command\DisableTwoFactor\DisableTwoFactor;
use App\Identity\Application\Command\EnableTwoFactor\EnableTwoFactor;
use App\Identity\Application\Command\RegenerateBackupCodes\RegenerateBackupCodes;
use App\Identity\Application\Exception\InvalidTotpCode;
use App\Identity\Application\Port\BackupCodeHasher;
use App\Identity\Application\Port\TotpProvisioner;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Command\CommandBus;
use App\Tests\Factory\Identity\UserFactory;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class TwoFactorEnrolmentTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testAValidCodeEnablesTheSecondFactor(): void
    {
        $user = UserFactory::createOne();
        $secret = $this->secret();

        $codes = $this->enable($user, $secret);

        $reloaded = $this->reload($user);
        self::assertTrue($reloaded->hasTwoFactorEnabled());
        self::assertSame($secret, $reloaded->totpSecret()?->toString());
        self::assertCount(3, $codes);
    }

    public function testAWrongCodeChangesNothing(): void
    {
        $user = UserFactory::createOne();

        try {
            $this->bus()->dispatch(new EnableTwoFactor($user->id()->toString(), $this->secret(), '000000'));
            self::fail('Un code erroné doit être refusé.');
        } catch (InvalidTotpCode) {
            // attendu
        }

        self::assertFalse(
            $this->reload($user)->hasTwoFactorEnabled(),
            'Un enrôlement raté ne doit rien écrire : sinon un QR jamais scanné verrouillerait le compte.',
        );
    }

    public function testTheBackupCodesAreStoredHashedNotInClear(): void
    {
        $user = UserFactory::createOne();

        $codes = $this->enable($user, $this->secret());

        $stored = $this->reload($user)->backupCodes();
        self::assertCount(3, $stored);

        foreach ($codes as $index => $plain) {
            self::assertNotSame($plain, $stored[$index], 'Un code de secours ne doit jamais être lisible en base.');
        }

        $hasher = self::getContainer()->get(BackupCodeHasher::class);
        self::assertInstanceOf(BackupCodeHasher::class, $hasher);
        self::assertTrue($hasher->verify($stored[0], $codes[0]));
    }

    public function testDisablingForgetsTheSecretAndTheCodes(): void
    {
        $user = UserFactory::createOne();
        $this->enable($user, $this->secret());

        $this->bus()->dispatch(new DisableTwoFactor($user->id()->toString()));

        $reloaded = $this->reload($user);
        self::assertFalse($reloaded->hasTwoFactorEnabled());
        self::assertSame([], $reloaded->backupCodes());
    }

    public function testRegeneratingInvalidatesThePreviousCodes(): void
    {
        $user = UserFactory::createOne();
        $first = $this->enable($user, $this->secret());
        $before = $this->reload($user)->backupCodes();

        /** @var list<string> $second */
        $second = $this->bus()->dispatch(new RegenerateBackupCodes($user->id()->toString()));

        self::assertNotSame($first, $second);
        self::assertNotSame($before, $this->reload($user)->backupCodes());
    }

    /**
     * @return list<string>
     */
    private function enable(User $user, string $secret): array
    {
        self::assertNotSame('', $secret);

        /** @var list<string> $codes */
        $codes = $this->bus()->dispatch(new EnableTwoFactor(
            $user->id()->toString(),
            $secret,
            TOTP::createFromSecret($secret)->now(),
        ));

        return $codes;
    }

    private function secret(): string
    {
        $provisioner = self::getContainer()->get(TotpProvisioner::class);
        self::assertInstanceOf(TotpProvisioner::class, $provisioner);

        return $provisioner->generateSecret()->toString();
    }

    private function reload(User $user): User
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);

        $reloaded = $users->ofId($user->id());
        self::assertInstanceOf(User::class, $reloaded);

        return $reloaded;
    }

    private function bus(): CommandBus
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);

        return $bus;
    }
}
