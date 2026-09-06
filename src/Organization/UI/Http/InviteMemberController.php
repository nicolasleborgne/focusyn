<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Command\InviteMember\InviteMember;
use App\Organization\Application\Exception\AlreadyInvited;
use App\Organization\UI\Security\OrganizationVoter;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ValueError;

#[IsGranted(OrganizationVoter::MANAGE_MEMBERS)]
final class InviteMemberController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/equipe/inviter', 'en' => '/team/invite'],
        name: 'team_invite',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('team-invite')]
    public function __invoke(Request $request): Response
    {
        try {
            $this->commands->dispatch(new InviteMember(
                $request->request->getString('email'),
                $request->request->getString('role'),
            ));
            $this->addFlash('success', 'team.invited');
        } catch (AlreadyInvited) {
            $this->addFlash('error', 'team.already_invited');
        } catch (InvalidArgumentException|ValueError) {
            $this->addFlash('error', 'team.invite_refused');
        }

        return $this->redirectToRoute('team');
    }
}
