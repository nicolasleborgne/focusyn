<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Command\ChangeMemberRole\ChangeMemberRole;
use App\Organization\Application\Command\RemoveMember\RemoveMember;
use App\Organization\Domain\Exception\NotAMember;
use App\Organization\Domain\Exception\OrganizationWouldLoseItsLastOwner;
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

/**
 * Changer le rôle d'un membre, ou le retirer.
 *
 * Un seul contrôleur pour les deux : c'est la même rangée de l'écran, et la
 * même autorisation.
 */
#[IsGranted(OrganizationVoter::MANAGE_MEMBERS)]
final class ManageMemberController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/equipe/membres', 'en' => '/team/members'],
        name: 'team_member',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('team-member')]
    public function __invoke(Request $request): Response
    {
        $memberId = $request->request->getString('memberId');

        try {
            $this->commands->dispatch($request->request->has('remove')
                ? new RemoveMember($memberId)
                : new ChangeMemberRole($memberId, $request->request->getString('role')));
            $this->addFlash('success', 'team.member_saved');
        } catch (OrganizationWouldLoseItsLastOwner) {
            $this->addFlash('error', 'team.last_owner');
        } catch (NotAMember|InvalidArgumentException|ValueError) {
            $this->addFlash('error', 'team.member_refused');
        }

        return $this->redirectToRoute('team');
    }
}
