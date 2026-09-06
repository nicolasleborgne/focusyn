<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\AcceptInvitation;

use App\Organization\Domain\Exception\AlreadyAMember;
use App\Organization\Domain\Exception\InvitationCannotBeAccepted;
use App\Organization\Domain\Model\InvitationToken;
use App\Organization\Domain\Model\InvitedEmail;
use App\Organization\Domain\Model\MemberId;
use App\Organization\Domain\Model\MembershipId;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\InvitationRepository;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Billing\Entitlements;
use App\Shared\Application\Billing\PlanLimitReached;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Rejoindre l'organisation qui vous a invité.
 *
 * Le jeton ne suffit pas : c'est l'agrégat qui vérifie que l'adresse du compte
 * connecté est bien celle qui a été invitée. Un lien transféré ne fait donc
 * entrer personne.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AcceptInvitationHandler
{
    public function __construct(
        private InvitationRepository $invitations,
        private OrganizationRepository $organizations,
        private CurrentAccount $account,
        private Entitlements $entitlements,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AcceptInvitation $command): OrganizationId
    {
        try {
            $token = InvitationToken::fromString($command->token);
        } catch (InvalidArgumentException) {
            throw InvitationCannotBeAccepted::becauseItWasAddressedToSomeoneElse();
        }

        $invitation = $this->invitations->ofToken($token)
            ?? throw InvitationCannotBeAccepted::becauseItWasAddressedToSomeoneElse();

        $email = $this->account->emailOrNull() ?? throw new InvalidArgumentException('Aucun compte connecté.');
        $accountId = $this->account->idOrNull() ?? throw new InvalidArgumentException('Aucun compte connecté.');
        $now = $this->clock->now();

        $invitation->acceptedBy(InvitedEmail::fromString($email), $now);

        $organization = $this->organizations->ofId($invitation->organizationId())
            ?? throw InvitationCannotBeAccepted::becauseItWasAddressedToSomeoneElse();

        $member = MemberId::fromString($accountId);

        // Les places sont comptées ici aussi : entre l'envoi et l'acceptation,
        // l'abonnement a pu retomber. On refuse avant d'enregistrer quoi que
        // ce soit — la transaction est annulée, l'invitation reste valable, et
        // elle entrera dès qu'une place se libère ou se paie.
        //
        // Le contrôle vient après la vérification d'adresse : à un lien
        // transféré, on ne doit même pas apprendre que l'équipe est complète.
        if (!$organization->hasMember($member)
            && \count($organization->memberships()) >= $this->entitlements->memberAllowanceOf($organization->id()->toString())) {
            throw PlanLimitReached::members();
        }

        try {
            $organization->addMember(
                MembershipId::generate(),
                $member,
                $invitation->role(),
                $now,
            );
        } catch (AlreadyAMember) {
            // Deux clics sur le même lien : l'invitation est consommée, la
            // place était déjà prise. Rien à signaler.
        }

        $this->organizations->save($organization);
        $this->invitations->save($invitation);

        return $organization->id();
    }
}
