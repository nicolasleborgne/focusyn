<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\InviteMember;

use App\Organization\Application\Exception\AlreadyInvited;
use App\Organization\Application\Port\InvitationLink;
use App\Organization\Application\Port\InvitationMailer;
use App\Organization\Application\Port\InvitationTokenGenerator;
use App\Organization\Domain\Model\Invitation;
use App\Organization\Domain\Model\InvitationId;
use App\Organization\Domain\Model\InvitedEmail;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizationRole;
use App\Organization\Domain\Repository\InvitationRepository;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Tenant\CurrentTenant;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class InviteMemberHandler
{
    public function __construct(
        private InvitationRepository $invitations,
        private OrganizationRepository $organizations,
        private InvitationTokenGenerator $tokens,
        private InvitationMailer $mailer,
        private InvitationLink $links,
        private CurrentTenant $tenant,
        private CurrentAccount $account,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(InviteMember $command): void
    {
        $organizationId = OrganizationId::fromString($this->tenant->id()->toString());
        $organization = $this->organizations->ofId($organizationId)
            ?? throw new InvalidArgumentException('Organisation introuvable.');

        $email = InvitedEmail::fromString($command->email);
        $role = OrganizationRole::from($command->role);
        $now = $this->clock->now();

        $existing = $this->invitations->pendingFor($organizationId, $email);

        if (null !== $existing) {
            if ($existing->isPendingAt($now)) {
                throw AlreadyInvited::withEmail($email->toString());
            }

            // Une invitation expirée ou déjà acceptée ne barre pas la route :
            // on la remplace, la contrainte d'unicité l'exige de toute façon.
            $this->invitations->remove($existing);
        }

        $invitation = Invitation::open(
            InvitationId::generate(),
            $organizationId,
            $email,
            $role,
            $this->tokens->generate(),
            MemberId::fromString($this->account->idOrNull() ?? throw new InvalidArgumentException('Aucun compte connecté.')),
            $now,
        );

        $this->invitations->save($invitation);
        $this->mailer->send($invitation, $organization->name(), $this->links->to($invitation));
    }
}
