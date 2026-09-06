<?php

declare(strict_types=1);

namespace App\Tests\Integration\Organization;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\MembershipId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizationRole;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Organization\UI\Security\OrganizationVoter;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Tenant\CurrentTenant;
use App\Shared\Application\Tenant\NoCurrentTenant;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ce que chaque rôle a le droit de faire, dans l'organisation courante.
 *
 * Vérifié ici plutôt qu'à travers l'écran : une requête forcée se heurterait
 * d'abord au jeton CSRF, ce qui ne prouverait rien de l'autorisation.
 */
final class OrganizationVoterTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testAnOwnerMayManageMembersAndAdminister(): void
    {
        [$organization, $owner] = $this->team();

        self::assertTrue($this->allows($organization, $owner, OrganizationVoter::MANAGE_MEMBERS));
        self::assertTrue($this->allows($organization, $owner, OrganizationVoter::ADMINISTER));
    }

    public function testAnAdministratorManagesMembersButNotTheOrganizationItself(): void
    {
        [$organization, , $admin] = $this->team();

        // Un administrateur ne doit pas pouvoir supprimer l'espace qui
        // l'héberge, ni en changer le propriétaire.
        self::assertTrue($this->allows($organization, $admin, OrganizationVoter::MANAGE_MEMBERS));
        self::assertFalse($this->allows($organization, $admin, OrganizationVoter::ADMINISTER));
    }

    public function testAPlainMemberManagesNothing(): void
    {
        [$organization, , , $member] = $this->team();

        self::assertFalse($this->allows($organization, $member, OrganizationVoter::MANAGE_MEMBERS));
        self::assertFalse($this->allows($organization, $member, OrganizationVoter::ADMINISTER));
    }

    public function testSomeoneOutsideTheOrganizationHasNoSayInIt(): void
    {
        [$organization] = $this->team();

        self::assertFalse($this->allows($organization, MemberId::generate(), OrganizationVoter::MANAGE_MEMBERS));
    }

    public function testWithoutAnyOrganizationNothingIsGranted(): void
    {
        [, $owner] = $this->team();

        // Hors organisation, le voteur refuse : échouer ouvert reviendrait à
        // tout autoriser à qui n'a pas encore choisi d'espace.
        $voter = new OrganizationVoter($this->organizations(), new FixedTenant(null), new FixedAccount($owner->toString()));

        self::assertNotSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($this->createStub(TokenInterface::class), null, [OrganizationVoter::MANAGE_MEMBERS]),
        );
    }

    public function testAVisitorHasNoSayAtAll(): void
    {
        [$organization] = $this->team();

        $voter = new OrganizationVoter(
            $this->organizations(),
            new FixedTenant(TenantId::fromString($organization->id()->toString())),
            new FixedAccount(null),
        );

        self::assertNotSame(
            Voter::ACCESS_GRANTED,
            $voter->vote($this->createStub(TokenInterface::class), null, [OrganizationVoter::MANAGE_MEMBERS]),
        );
    }

    /** @return array{Organization, MemberId, MemberId, MemberId} */
    private function team(): array
    {
        $owner = MemberId::generate();
        $admin = MemberId::generate();
        $member = MemberId::generate();
        $now = new DateTimeImmutable('2026-09-06 09:00');

        $organization = Organization::create(
            OrganizationId::generate(),
            'Le studio',
            'le-studio',
            $owner,
            MembershipId::generate(),
            $now,
        );
        $organization->addMember(MembershipId::generate(), $admin, OrganizationRole::Admin, $now);
        $organization->addMember(MembershipId::generate(), $member, OrganizationRole::Member, $now);

        $organizations = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $organizations);
        $organizations->save($organization);

        return [$organization, $owner, $admin, $member];
    }

    private function allows(Organization $organization, MemberId $member, string $attribute): bool
    {
        $voter = new OrganizationVoter(
            $this->organizations(),
            new FixedTenant(TenantId::fromString($organization->id()->toString())),
            new FixedAccount($member->toString()),
        );

        // Le voteur ne regarde pas le jeton : il lit l'organisation courante
        // et le compte connecté. Un simple bouchon suffit donc.
        return Voter::ACCESS_GRANTED === $voter->vote($this->createStub(TokenInterface::class), null, [$attribute]);
    }

    private function organizations(): OrganizationRepository
    {
        $organizations = self::getContainer()->get(OrganizationRepository::class);
        self::assertInstanceOf(OrganizationRepository::class, $organizations);

        return $organizations;
    }
}

/** L'organisation courante, fixée : le voteur n'a pas à connaître la session. */
final readonly class FixedTenant implements CurrentTenant
{
    public function __construct(private ?TenantId $tenant)
    {
    }

    public function id(): TenantId
    {
        return $this->tenant ?? throw NoCurrentTenant::create();
    }

    public function idOrNull(): ?TenantId
    {
        return $this->tenant;
    }
}

final readonly class FixedAccount implements CurrentAccount
{
    public function __construct(private ?string $id)
    {
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->id;
    }

    public function emailOrNull(): ?string
    {
        return null === $this->id ? null : 'quelquun@focusyn.fr';
    }

    public function idOrNull(): ?string
    {
        return $this->id;
    }
}
