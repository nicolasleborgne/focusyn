<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Command\AcceptInvitation\AcceptInvitation;
use App\Organization\Application\Command\SwitchOrganization\SwitchOrganization;
use App\Organization\Domain\Exception\InvitationCannotBeAccepted;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Accepter une invitation depuis le lien reçu par courriel.
 *
 * En GET, et sans jeton CSRF : c'est un lien cliqué dans une boîte aux
 * lettres. Ce qui protège n'est pas le formulaire mais l'agrégat — l'adresse
 * du compte connecté doit être celle qui a été invitée.
 */
final class AcceptInvitationController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
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
