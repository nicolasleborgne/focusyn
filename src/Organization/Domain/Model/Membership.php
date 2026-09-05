<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

use DateTimeImmutable;

/**
 * Appartenance d'une personne à une organisation.
 *
 * Entité interne à l'agrégat Organization : elle n'a pas d'existence propre et
 * ne se charge jamais seule — c'est l'organisation qui garantit qu'il reste
 * toujours au moins un propriétaire.
 *
 * La référence arrière vers l'organisation n'est pas un choix de modélisation
 * mais une contrainte de l'ORM : Doctrine n'adosse une association un-à-plusieurs
 * à une clé étrangère que si elle est bidirectionnelle. Elle reste privée.
 */
final class Membership
{
    public function __construct(
        private readonly Organization $organization,
        private readonly MembershipId $id,
        private readonly MemberId $memberId,
        private OrganizationRole $role,
        private readonly DateTimeImmutable $joinedAt,
    ) {
    }

    public function organization(): Organization
    {
        return $this->organization;
    }

    public function id(): MembershipId
    {
        return $this->id;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }

    public function role(): OrganizationRole
    {
        return $this->role;
    }

    public function joinedAt(): DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function isOwner(): bool
    {
        return OrganizationRole::Owner === $this->role;
    }

    public function assignRole(OrganizationRole $role): void
    {
        $this->role = $role;
    }
}
