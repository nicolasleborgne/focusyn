<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Command\CreateOrganization\CreateOrganization;
use App\Organization\Application\Command\SwitchOrganization\SwitchOrganization;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Ouvrir une organisation d'équipe.
 *
 * Aucune autorisation à vérifier : n'importe qui peut créer la sienne, il en
 * devient propriétaire. On y bascule aussitôt — la créer sans y entrer serait
 * une étape de plus pour rien.
 */
final class CreateOrganizationController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/equipe/nouvelle', 'en' => '/team/new'],
        name: 'team_create',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('team-create')]
    public function __invoke(Request $request): Response
    {
        try {
            $created = $this->commands->dispatch(new CreateOrganization($request->request->getString('name')));
            $this->commands->dispatch(new SwitchOrganization((string) $created));
            $this->addFlash('success', 'team.created');
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'team.name_refused');
        }

        return $this->redirectToRoute('team');
    }
}
