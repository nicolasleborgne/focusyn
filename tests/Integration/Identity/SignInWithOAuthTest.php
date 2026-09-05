<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Command\SignInWithOAuth\SignInWithOAuth;
use App\Identity\Application\Exception\UnverifiedOAuthEmail;
use App\Identity\Domain\Model\OAuthProvider;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Command\CommandBus;
use App\Tests\Factory\Identity\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Les trois chemins d'une connexion externe, et le refus qui protège le
 * quatrième.
 */
final class SignInWithOAuthTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testAnUnknownProviderOpensANewAccount(): void
    {
        $userId = $this->signIn('google', 'g-1', 'nouvelle@focusyn.fr', verified: true);

        $user = $this->users()->ofId($userId);
        self::assertNotNull($user);
        self::assertSame('nouvelle@focusyn.fr', $user->email()->toString());
        self::assertTrue($user->hasOAuthIdentity(OAuthProvider::Google));
    }

    public function testANewAccountAlsoGetsItsPersonalOrganization(): void
    {
        $userId = $this->signIn('google', 'g-1', 'nouvelle@focusyn.fr', verified: true);

        $organizations = $this->organizations()->ofMember(MemberId::fromString($userId->toString()));

        self::assertCount(1, $organizations, 'Une inscription par fournisseur externe reste une inscription.');
        self::assertTrue($organizations[0]->isPersonal());
    }

    public function testAnAlreadyLinkedProviderSignsTheSameAccountBackIn(): void
    {
        $first = $this->signIn('google', 'g-1', 'nicolas@focusyn.fr', verified: true);

        $second = $this->signIn('google', 'g-1', 'nicolas@focusyn.fr', verified: true);

        self::assertTrue($first->equals($second));
        self::assertCount(1, $this->reload($first)->oauthIdentities());
    }

    public function testAVerifiedAddressLinksToTheExistingAccount(): void
    {
        $existing = UserFactory::new()->withEmail('nicolas@focusyn.fr')->create();

        $userId = $this->signIn('github', 'gh-9', 'nicolas@focusyn.fr', verified: true);

        self::assertTrue($existing->id()->equals($userId));
        self::assertTrue($this->reload($userId)->hasOAuthIdentity(OAuthProvider::GitHub));
    }

    public function testAnUnverifiedAddressNeverTakesOverAnExistingAccount(): void
    {
        UserFactory::new()->withEmail('nicolas@focusyn.fr')->create();

        $this->expectException(UnverifiedOAuthEmail::class);

        $this->signIn('github', 'gh-9', 'nicolas@focusyn.fr', verified: false);
    }

    public function testTwoProvidersCanCoexistOnOneAccount(): void
    {
        $userId = $this->signIn('google', 'g-1', 'nicolas@focusyn.fr', verified: true);

        $this->signIn('github', 'gh-9', 'nicolas@focusyn.fr', verified: true);

        self::assertCount(2, $this->reload($userId)->oauthIdentities());
    }

    public function testTheSameProviderAccountCannotServeTwoFocusynAccounts(): void
    {
        $this->signIn('google', 'g-1', 'premiere@focusyn.fr', verified: true);

        // Le même compte Google, présenté avec une autre adresse : la première
        // identité rattachée l'emporte, aucun second compte n'est créé.
        $second = $this->signIn('google', 'g-1', 'seconde@focusyn.fr', verified: true);

        self::assertSame('premiere@focusyn.fr', $this->reload($second)->email()->toString());
        self::assertNull($this->users()->ofEmail(
            \App\Identity\Domain\Model\EmailAddress::fromString('seconde@focusyn.fr'),
        ));
    }

    public function testAccountsCreatedByAProviderCarryAnUnguessablePassword(): void
    {
        $userId = $this->signIn('google', 'g-1', 'nouvelle@focusyn.fr', verified: true);

        $hash = $this->reload($userId)->password()->toString();

        self::assertNotSame('', $hash);
        self::assertStringStartsWith('$', $hash);
    }

    public function testLinkingIsIdempotentAcrossDistinctExternalIds(): void
    {
        $userId = $this->signIn('google', 'g-1', 'nicolas@focusyn.fr', verified: true);

        // Un autre compte Google prétendant la même adresse vérifiée : le
        // fournisseur est déjà rattaché, l'agrégat refuse le doublon.
        $this->expectException(\App\Identity\Domain\Exception\OAuthProviderAlreadyLinked::class);

        $this->signIn('google', 'g-2', 'nicolas@focusyn.fr', verified: true);

        self::assertCount(1, $this->reload($userId)->oauthIdentities());
    }

    private function signIn(string $provider, string $externalId, string $email, bool $verified): UserId
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);

        $userId = $bus->dispatch(new SignInWithOAuth($provider, $externalId, $email, $verified));
        self::assertInstanceOf(UserId::class, $userId);

        return $userId;
    }

    private function reload(UserId $userId): User
    {
        $user = $this->users()->ofId($userId);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function users(): UserRepository
    {
        $repository = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }

    private function organizations(): OrganizationRepository
    {
        $repository = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $repository);

        return $repository;
    }
}
