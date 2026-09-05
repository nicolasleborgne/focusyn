<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Exception\OAuthProviderAlreadyLinked;
use App\Identity\Domain\Exception\OAuthProviderNotLinked;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\HashedPassword;
use App\Identity\Domain\Model\OAuthIdentityId;
use App\Identity\Domain\Model\OAuthProvider;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserOAuthTest extends TestCase
{
    public function testANewAccountHasNoLinkedProvider(): void
    {
        $user = self::user();

        self::assertSame([], $user->oauthIdentities());
        self::assertFalse($user->hasOAuthIdentity(OAuthProvider::Google));
    }

    public function testAProviderCanBeLinked(): void
    {
        $user = self::user();

        $user->linkOAuthIdentity(OAuthIdentityId::generate(), OAuthProvider::Google, '10937', self::now());

        self::assertTrue($user->hasOAuthIdentity(OAuthProvider::Google));
        self::assertCount(1, $user->oauthIdentities());
        self::assertSame('10937', $user->oauthIdentities()[0]->externalId());
    }

    public function testTwoDifferentProvidersCanCoexist(): void
    {
        $user = self::user();

        $user->linkOAuthIdentity(OAuthIdentityId::generate(), OAuthProvider::Google, '10937', self::now());
        $user->linkOAuthIdentity(OAuthIdentityId::generate(), OAuthProvider::GitHub, 'octo-42', self::now());

        self::assertCount(2, $user->oauthIdentities());
    }

    public function testTheSameProviderCannotBeLinkedTwice(): void
    {
        $user = self::user();
        $user->linkOAuthIdentity(OAuthIdentityId::generate(), OAuthProvider::Google, '10937', self::now());

        $this->expectException(OAuthProviderAlreadyLinked::class);

        $user->linkOAuthIdentity(OAuthIdentityId::generate(), OAuthProvider::Google, 'un-autre', self::now());
    }

    public function testAProviderCanBeUnlinked(): void
    {
        $user = self::user();
        $user->linkOAuthIdentity(OAuthIdentityId::generate(), OAuthProvider::Google, '10937', self::now());
        $user->linkOAuthIdentity(OAuthIdentityId::generate(), OAuthProvider::GitHub, 'octo-42', self::now());

        $user->unlinkOAuthIdentity(OAuthProvider::Google);

        self::assertFalse($user->hasOAuthIdentity(OAuthProvider::Google));
        self::assertTrue($user->hasOAuthIdentity(OAuthProvider::GitHub));
    }

    public function testUnlinkingWhatIsNotLinkedIsRefused(): void
    {
        $this->expectException(OAuthProviderNotLinked::class);

        self::user()->unlinkOAuthIdentity(OAuthProvider::GitHub);
    }

    public function testEachProviderIsIdentifiedByAStableKey(): void
    {
        self::assertSame('google', OAuthProvider::Google->value);
        self::assertSame('github', OAuthProvider::GitHub->value);
    }

    private static function user(): User
    {
        return User::register(
            UserId::generate(),
            EmailAddress::fromString('nicolas@focusyn.fr'),
            HashedPassword::fromHash('empreinte'),
            self::now(),
        );
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-05 10:00:00');
    }
}
