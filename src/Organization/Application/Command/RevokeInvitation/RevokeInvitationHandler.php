<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\RevokeInvitation;

use App\Organization\Domain\Repository\InvitationRepository;
use App\Shared\Application\Tenant\CurrentTenant;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RevokeInvitationHandler
{
    public function __construct(
        private InvitationRepository $invitations,
        private CurrentTenant $tenant,
    ) {
    }

    public function __invoke(RevokeInvitation $command): void
    {
        $invitation = $this->invitations->ofId($command->invitationId);

        // Les invitations ne sont pas cloisonnées par le filtre Doctrine : on
        // vérifie donc à la main qu'elle appartient bien à l'organisation
        // courante, sinon un identifiant deviné suffirait.
        if (null !== $invitation && $invitation->organizationId()->toString() === $this->tenant->id()->toString()) {
            $this->invitations->remove($invitation);
        }
    }
}
