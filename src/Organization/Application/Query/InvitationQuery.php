<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

use App\Organization\Domain\Model\InvitationToken;
use App\Organization\Domain\Repository\InvitationRepository;
use App\Organization\Domain\Repository\OrganizationRepository;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final readonly class InvitationQuery
{
    public function __construct(
        private InvitationRepository $invitations,
        private OrganizationRepository $organizations,
        private ClockInterface $clock,
    ) {
    }

    public function byToken(string $token): ?PendingInvitationView
    {
        try {
            $invitation = $this->invitations->ofToken(InvitationToken::fromString($token));
        } catch (InvalidArgumentException) {
            return null;
        }

        if (null === $invitation) {
            return null;
        }

        $organization = $this->organizations->ofId($invitation->organizationId());

        if (null === $organization) {
            return null;
        }

        return new PendingInvitationView(
            organizationName: $organization->name(),
            email: $invitation->email()->toString(),
            role: $invitation->role(),
            stillValid: $invitation->isPendingAt($this->clock->now()),
        );
    }
}
