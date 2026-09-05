<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Event\UserWasRegistered;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\HashedPassword;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    private const string REGISTERED_AT = '2026-09-05 10:00:00';

    public function testRegisteringAUserAnnouncesIt(): void
    {
        $id = UserId::generate();

        $user = self::register($id);

        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserWasRegistered::class, $events[0]);
        self::assertSame($id->toString(), $events[0]->userId);
        self::assertSame('nicolas@focusyn.fr', $events[0]->email);
    }

    public function testTheEventCarriesTheMomentOfRegistration(): void
    {
        $user = self::register();

        $events = $user->releaseEvents();
        self::assertInstanceOf(UserWasRegistered::class, $events[0]);
        self::assertSame(self::REGISTERED_AT, $events[0]->occurredAt()->format('Y-m-d H:i:s'));
    }

    public function testANewUserExposesWhatIdentifiesIt(): void
    {
        $id = UserId::generate();

        $user = self::register($id);

        self::assertTrue($id->equals($user->id()));
        self::assertSame('nicolas@focusyn.fr', $user->email()->toString());
        self::assertSame('empreinte-argon2', $user->password()->toString());
    }

    public function testChangingThePasswordReplacesTheStoredFingerprint(): void
    {
        $user = self::register();
        $user->releaseEvents();

        $user->changePassword(HashedPassword::fromHash('nouvelle-empreinte'));

        self::assertSame('nouvelle-empreinte', $user->password()->toString());
    }

    public function testChangingTheEmailNormalisesTheNewOne(): void
    {
        $user = self::register();

        $user->changeEmail(EmailAddress::fromString('  Nouvelle@Focusyn.FR '));

        self::assertSame('nouvelle@focusyn.fr', $user->email()->toString());
    }

    public function testChangingTheEmailToTheSameOneChangesNothing(): void
    {
        $user = self::register();
        $user->releaseEvents();

        $user->changeEmail(EmailAddress::fromString('NICOLAS@focusyn.fr'));

        self::assertSame([], $user->releaseEvents());
    }

    public function testANewAccountStartsWithTheDesignDefaults(): void
    {
        self::assertTrue(
            \App\Identity\Domain\Model\DisplayPreferences::default()->equals(self::register()->display()),
        );
    }

    public function testDisplayPreferencesCanBeAdjusted(): void
    {
        $user = self::register();

        $user->adjustDisplay(
            \App\Identity\Domain\Model\DisplayPreferences::default()
                ->withAccent(\App\Identity\Domain\Model\Accent::Olive),
        );

        self::assertSame(\App\Identity\Domain\Model\Accent::Olive, $user->display()->accent);
    }

    private static function register(?UserId $id = null): User
    {
        return User::register(
            $id ?? UserId::generate(),
            EmailAddress::fromString('nicolas@focusyn.fr'),
            HashedPassword::fromHash('empreinte-argon2'),
            new DateTimeImmutable(self::REGISTERED_AT),
        );
    }
}
