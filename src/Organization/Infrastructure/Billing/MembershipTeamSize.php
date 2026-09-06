<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Billing;

use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Team\TeamSize;
use App\Shared\Application\Tenant\CurrentTenant;

final readonly class MembershipTeamSize implements TeamSize
{
    public function __construct(
        private OrganizationRepository $organizations,
        private CurrentTenant $tenant,
    ) {
    }

    public function size(): int
    {
        $organization = $this->tenant->idOrNull();

        if (null === $organization) {
            return 1;
        }

        $found = $this->organizations->ofId(OrganizationId::fromString($organization->toString()));

        // Une organisation sans membre n'existe pas : la valeur de repli est
        // une place, jamais zéro, qui donnerait une facture à zéro euro.
        return null === $found ? 1 : max(1, \count($found->memberships()));
    }
}
