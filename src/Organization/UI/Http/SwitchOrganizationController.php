<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Command\SwitchOrganization\SwitchOrganization;
use App\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class SwitchOrganizationController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
    ) {
    }

    #[Route(
        path: ['fr' => '/equipe/basculer', 'en' => '/team/switch'],
        name: 'team_switch',
        methods: ['POST'],
    )]
    #[IsCsrfTokenValid('team-switch')]
    public function __invoke(Request $request): Response
    {
        $this->commands->dispatch(new SwitchOrganization($request->request->getString('organizationId')));

        return $this->redirectToRoute('home');
    }
}
