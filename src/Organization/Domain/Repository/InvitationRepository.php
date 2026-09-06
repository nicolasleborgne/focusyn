<?php

declare(strict_types=1);

namespace App\Organization\Domain\Repository;

use App\Organization\Domain\Model\Invitation;
use App\Organization\Domain\Model\InvitationToken;
use App\Organization\Domain\Model\InvitedEmail;
use App\Organization\Domain\Model\OrganizationId;

/**
 * Aucune requête n'est cloisonnée : toutes portent une organisation ou un
 * jeton explicite. C'est ce qui permet à un invité, pas encore membre, de
 * retrouver l'invitation qui lui est faite.
 */
interface InvitationRepository
{
    public function save(Invitation $invitation): void;

    public function remove(Invitation $invitation): void;

    public function ofToken(InvitationToken $token): ?Invitation;

    public function ofId(string $id): ?Invitation;

    public function pendingFor(OrganizationId $organizationId, InvitedEmail $email): ?Invitation;

    /** @return list<Invitation> */
    public function ofOrganization(OrganizationId $organizationId): array;
}
