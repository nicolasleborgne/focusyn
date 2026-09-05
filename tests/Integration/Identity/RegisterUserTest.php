<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Command\RegisterUser\RegisterUser;
use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * L'inscription est le premier cas d'usage qui traverse deux contextes :
 * Identity crée le compte, Organization en déduit l'espace personnel, sans que
 * l'un connaisse les classes de l'autre.
 */
final class RegisterUserTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testItCreatesTheAccount(): void
    {
        $userId = $this->register('nicolas@focusyn.fr', 'une phrase de passe');

        self::assertInstanceOf(UserId::class, $userId);

        $user = $this->users()->ofId($userId);
        self::assertNotNull($user);
        self::assertSame('nicolas@focusyn.fr', $user->email()->toString());
    }

    public function testThePasswordIsStoredHashedAndVerifiable(): void
    {
        $userId = $this->register('nicolas@focusyn.fr', 'une phrase de passe');
        self::assertInstanceOf(UserId::class, $userId);

        $user = $this->users()->ofId($userId);
        self::assertNotNull($user);

        self::assertStringNotContainsString(
            'une phrase de passe',
            $user->password()->toString(),
            'Le mot de passe en clair ne doit jamais atteindre la base.',
        );

        $hasher = self::getContainer()->get(PasswordHasher::class);
        self::assertInstanceOf(PasswordHasher::class, $hasher);
        self::assertTrue($hasher->verify($user->password(), 'une phrase de passe'));
    }

    public function testItOpensAPersonalOrganizationForTheNewAccount(): void
    {
        $userId = $this->register('nicolas@focusyn.fr', 'une phrase de passe');
        self::assertInstanceOf(UserId::class, $userId);

        $organizations = $this->organizations()->ofMember(MemberId::fromString($userId->toString()));

        self::assertCount(1, $organizations);
        self::assertTrue($organizations[0]->isPersonal());
        self::assertSame('Nicolas', $organizations[0]->name());
        self::assertSame('nicolas', $organizations[0]->slug());
    }

    public function testTheNewAccountOwnsItsPersonalOrganization(): void
    {
        $userId = $this->register('nicolas@focusyn.fr', 'une phrase de passe');
        self::assertInstanceOf(UserId::class, $userId);
        $member = MemberId::fromString($userId->toString());

        $organizations = $this->organizations()->ofMember($member);

        self::assertSame('owner', $organizations[0]->roleOf($member)?->value);
    }

    public function testTwoAccountsWithTheSameLocalPartGetDistinctSlugs(): void
    {
        $this->register('nicolas@focusyn.fr', 'une phrase de passe');
        $second = $this->register('nicolas@example.org', 'une autre phrase');
        self::assertInstanceOf(UserId::class, $second);

        $organizations = $this->organizations()->ofMember(MemberId::fromString($second->toString()));

        self::assertSame(
            'nicolas-2',
            $organizations[0]->slug(),
            'Deux espaces personnels homonymes doivent rester distinguables dans une URL.',
        );
    }

    public function testTheSameEmailCannotBeRegisteredTwice(): void
    {
        $this->register('nicolas@focusyn.fr', 'une phrase de passe');

        // Le bus de commandes déballe l'exception de Messenger : l'appelant
        // attrape bien l'exception métier, pas une exception de transport.
        $this->expectException(EmailAlreadyRegistered::class);

        $this->register('NICOLAS@Focusyn.FR', 'une autre phrase');
    }

    public function testARefusedRegistrationLeavesNothingBehind(): void
    {
        $this->register('nicolas@focusyn.fr', 'une phrase de passe');

        try {
            $this->register('nicolas@focusyn.fr', 'une autre phrase');
        } catch (EmailAlreadyRegistered) {
            // attendu
        }

        self::assertCount(
            1,
            $this->organizations()->ofMember(MemberId::fromString(
                ($this->users()->ofEmail(EmailAddress::fromString('nicolas@focusyn.fr'))?->id() ?? UserId::generate())->toString(),
            )),
        );
    }

    private function register(string $email, string $password): mixed
    {
        $bus = self::getContainer()->get(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);

        return $bus->dispatch(new RegisterUser($email, $password));
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
