<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Command\AcceptInvitation\AcceptInvitation;
use App\Organization\Application\Command\SwitchOrganization\SwitchOrganization;
use App\Organization\Application\Port\PendingInvitation;
use App\Organization\Application\Query\InvitationQuery;
use App\Organization\Domain\Exception\InvitationCannotBeAccepted;
use App\Shared\Application\Account\CurrentAccount;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Accepter une invitation depuis le lien reçu par courriel.
 *
 * Adresse publique : le lien arrive dans une boîte aux lettres, et rien ne dit
 * qu'on est déjà connecté — ni même qu'on a un compte. Sans session, on
 * présente l'invitation et on la met de côté ; elle sera reprise juste après la
 * connexion ou l'inscription.
 *
 * Ce n'est pas le formulaire qui protège mais l'agrégat : l'adresse du compte
 * qui accepte doit être celle qui a été invitée.
 */
final class AcceptInvitationController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly InvitationQuery $invitations,
        private readonly PendingInvitation $pending,
        private readonly CurrentAccount $account,
    ) {
    }

    #[Route(
        path: ['fr' => '/invitations/{token}', 'en' => '/invitations/{token}'],
        name: 'invitation_accept',
        requirements: ['token' => '[A-Za-z0-9_-]{32}'],
        methods: ['GET'],
    )]
    public function __invoke(string $token): Response
    {
        $invitation = $this->invitations->byToken($token);

        if (null === $invitation || !$invitation->stillValid) {
            return $this->render(
                'organization/invitation.html.twig',
                ['invitation' => $invitation],
                new Response(status: Response::HTTP_GONE),
            );
        }

        if (!$this->account->isAuthenticated()) {
            // On retient le jeton et on montre de quoi il retourne : demander
            // de se connecter sans dire à quoi serait décourageant.
            $this->pending->remember($token);

            return $this->render('organization/invitation.html.twig', ['invitation' => $invitation]);
        }

        try {
            $joined = $this->commands->dispatch(new AcceptInvitation($token));
            $this->commands->dispatch(new SwitchOrganization((string) $joined));
            $this->addFlash('success', 'team.joined_notice');

            return $this->redirectToRoute('home');
        } catch (InvitationCannotBeAccepted $refusal) {
            $this->addFlash('error', $refusal->getMessage());

            return $this->redirectToRoute('team');
        }
    }
}
