<?php

declare(strict_types=1);

namespace App\Organization\Domain\Repository;

use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;

interface OrganizationRepository
{
    public function save(Organization $organization): void;

    public function ofId(OrganizationId $id): ?Organization;

    public function ofSlug(string $slug): ?Organization;

    /**
     * Organisations dont la personne est membre, de la plus ancienne à la plus
     * récente.
     *
     * @return list<Organization>
     */
    public function ofMember(MemberId $member): array;

    public function slugIsTaken(string $slug): bool;
}
