<?php

declare(strict_types=1);

namespace App\Organization\Application\Query;

use App\Organization\Domain\Model\Invitation;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\Membership;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\InvitationRepository;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Account\AccountDirectory;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Tenant\CurrentTenant;
use Psr\Clock\ClockInterface;

/**
 * L'équipe telle que l'écran la présente.
 *
 * Les adresses viennent d'Identity par le port `AccountDirectory` :
 * Organization ne retient que des identifiants, et n'a pas le droit de
 * connaître ce qu'est un compte.
 */
final readonly class TeamQuery
{
    public function __construct(
        private OrganizationRepository $organizations,
        private InvitationRepository $invitations,
        private AccountDirectory $accounts,
        private CurrentTenant $tenant,
        private CurrentAccount $account,
        private ClockInterface $clock,
    ) {
    }

    public function current(): ?TeamView
    {
        $tenant = $this->tenant->idOrNull();
        $accountId = $this->account->idOrNull();

        if (null === $tenant || null === $accountId) {
            return null;
        }

        $organization = $this->organizations->ofId(OrganizationId::fromString($tenant->toString()));

        if (null === $organization) {
            return null;
        }

        return new TeamView(
            id: $organization->id()->toString(),
            name: $organization->name(),
            personal: $organization->isPersonal(),
            members: $this->membersOf($organization, $accountId),
            invitations: $this->invitationsOf($organization),
            spaces: $this->spacesOf($accountId, $organization->id()->toString()),
        );
    }

    /** @return list<MemberEntry> */
    private function membersOf(Organization $organization, string $accountId): array
    {
        return array_map(
            fn (Membership $membership): MemberEntry => new MemberEntry(
                id: $membership->memberId()->toString(),
                // Un compte supprimé laisse son appartenance : on le dit plutôt
                // que de faire disparaître la ligne.
                email: $this->accounts->emailOf($membership->memberId()->toString()) ?? '—',
                role: $membership->role(),
                joinedAt: $membership->joinedAt(),
                itsYou: $membership->memberId()->toString() === $accountId,
            ),
            $organization->memberships(),
        );
    }

    /** @return list<InvitationEntry> */
    private function invitationsOf(Organization $organization): array
    {
        $now = $this->clock->now();

        return array_values(array_map(
            static fn (Invitation $invitation): InvitationEntry => new InvitationEntry(
                id: $invitation->id()->toString(),
                email: $invitation->email()->toString(),
                role: $invitation->role(),
                expiresAt: $invitation->expiresAt(),
                expired: !$invitation->isPendingAt($now),
            ),
            array_filter(
                $this->invitations->ofOrganization($organization->id()),
                static fn (Invitation $invitation): bool => !$invitation->isAccepted(),
            ),
        ));
    }

    /** @return list<SpaceEntry> */
    private function spacesOf(string $accountId, string $currentId): array
    {
        return array_map(
            static fn (Organization $organization): SpaceEntry => new SpaceEntry(
                id: $organization->id()->toString(),
                name: $organization->name(),
                personal: $organization->isPersonal(),
                current: $organization->id()->toString() === $currentId,
            ),
            $this->organizations->ofMember(MemberId::fromString($accountId)),
        );
    }
}
