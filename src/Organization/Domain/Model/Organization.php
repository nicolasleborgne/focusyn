<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

use App\Organization\Domain\Event\OrganizationWasCreated;
use App\Organization\Domain\Exception\AlreadyAMember;
use App\Organization\Domain\Exception\NotAMember;
use App\Organization\Domain\Exception\OrganizationWouldLoseItsLastOwner;
use App\Shared\Domain\AggregateRoot;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;

/**
 * Un espace de travail : ce qui délimite ce qu'un utilisateur peut voir.
 *
 * Les appartenances sont tenues à l'intérieur de l'agrégat, et non dans un
 * agrégat séparé, parce qu'une règle les lie entre elles : une organisation
 * conserve toujours au moins un propriétaire. Cette invariante ne peut être
 * garantie que si un seul objet arbitre les retraits et les changements de rôle.
 */
final class Organization extends AggregateRoot
{
    /**
     * Collection Doctrine plutôt que tableau : c'est la seule structure que
     * l'ORM sait hydrater paresseusement. `doctrine/collections` est une
     * bibliothèque de structures de données, sans lien avec la base — c'est à
     * ce titre qu'elle est admise dans le domaine (voir deptrac.yaml).
     *
     * @var Collection<int, Membership>
     */
    private Collection $memberships;

    private function __construct(
        private readonly OrganizationId $id,
        private string $name,
        private string $slug,
        private readonly bool $personal,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->memberships = new ArrayCollection();
    }

    public static function create(
        OrganizationId $id,
        string $name,
        string $slug,
        MemberId $owner,
        MembershipId $ownerMembershipId,
        DateTimeImmutable $createdAt,
    ): self {
        return self::open($id, $name, $slug, $owner, $ownerMembershipId, $createdAt, personal: false);
    }

    /**
     * Espace créé automatiquement à l'inscription, pour que personne ne se
     * retrouve sans organisation — un compte sans espace ne pourrait rien écrire.
     */
    public static function createPersonal(
        OrganizationId $id,
        string $name,
        string $slug,
        MemberId $owner,
        MembershipId $ownerMembershipId,
        DateTimeImmutable $createdAt,
    ): self {
        return self::open($id, $name, $slug, $owner, $ownerMembershipId, $createdAt, personal: true);
    }

    public function id(): OrganizationId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function isPersonal(): bool
    {
        return $this->personal;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<Membership> */
    public function memberships(): array
    {
        return array_values($this->memberships->toArray());
    }

    public function hasMember(MemberId $member): bool
    {
        return null !== $this->membershipOf($member);
    }

    public function roleOf(MemberId $member): ?OrganizationRole
    {
        return $this->membershipOf($member)?->role();
    }

    public function addMember(
        MembershipId $membershipId,
        MemberId $member,
        OrganizationRole $role,
        DateTimeImmutable $joinedAt,
    ): void {
        if ($this->hasMember($member)) {
            throw AlreadyAMember::create();
        }

        $this->memberships->add(new Membership($this, $membershipId, $member, $role, $joinedAt));
    }

    public function removeMember(MemberId $member): void
    {
        $membership = $this->membershipOf($member) ?? throw NotAMember::create();

        if ($membership->isOwner() && 1 === $this->ownerCount()) {
            throw OrganizationWouldLoseItsLastOwner::create();
        }

        $this->memberships->removeElement($membership);
    }

    public function changeRole(MemberId $member, OrganizationRole $role): void
    {
        $membership = $this->membershipOf($member) ?? throw NotAMember::create();

        if ($membership->isOwner() && OrganizationRole::Owner !== $role && 1 === $this->ownerCount()) {
            throw OrganizationWouldLoseItsLastOwner::create();
        }

        $membership->assignRole($role);
    }

    public function rename(string $name, string $slug): void
    {
        $name = trim($name);

        if ('' === $name) {
            throw new InvalidArgumentException('Le nom d\'une organisation ne peut pas être vide.');
        }

        $this->name = $name;
        $this->slug = $slug;
    }

    private static function open(
        OrganizationId $id,
        string $name,
        string $slug,
        MemberId $owner,
        MembershipId $ownerMembershipId,
        DateTimeImmutable $createdAt,
        bool $personal,
    ): self {
        $trimmed = trim($name);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Le nom d\'une organisation ne peut pas être vide.');
        }

        $organization = new self($id, $trimmed, $slug, $personal, $createdAt);
        $organization->memberships->add(new Membership($organization, $ownerMembershipId, $owner, OrganizationRole::Owner, $createdAt));
        $organization->recordThat(new OrganizationWasCreated($id->toString(), $trimmed, $personal, $createdAt));

        return $organization;
    }

    private function membershipOf(MemberId $member): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->memberId()->equals($member)) {
                return $membership;
            }
        }

        return null;
    }

    private function ownerCount(): int
    {
        return $this->memberships
            ->filter(static fn (Membership $membership): bool => $membership->isOwner())
            ->count();
    }
}
