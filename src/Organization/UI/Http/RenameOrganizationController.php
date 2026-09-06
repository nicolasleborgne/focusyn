<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Command\RenameOrganization\RenameOrganization;
use App\Organization\UI\Security\OrganizationVoter;
use App\Shared\Application\Command\CommandBus;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(OrganizationVoter::ADMINISTER)]
final class RenameOrganizationController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/equipe/nom', 'en' => '/team/name'],
        name: 'team_rename',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('team-rename')]
    public function __invoke(Request $request): Response
    {
        try {
            $this->commands->dispatch(new RenameOrganization($request->request->getString('name')));
            $this->addFlash('success', 'team.renamed');
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'team.name_refused');
        }

        return $this->redirectToRoute('team');
    }
}
