<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Command\RevokeInvitation\RevokeInvitation;
use App\Organization\UI\Security\OrganizationVoter;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(OrganizationVoter::MANAGE_MEMBERS)]
final class RevokeInvitationController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/equipe/invitations/annuler', 'en' => '/team/invitations/revoke'],
        name: 'team_invitation_revoke',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('team-invitation-revoke')]
    public function __invoke(Request $request): Response
    {
        $this->commands->dispatch(new RevokeInvitation($request->request->getString('invitationId')));
        $this->addFlash('success', 'team.invitation_revoked');

        return $this->redirectToRoute('team');
    }
}
