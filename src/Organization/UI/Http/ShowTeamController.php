<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Query\TeamQuery;
use App\Organization\Domain\Model\OrganizationRole;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowTeamController extends AbstractController
{
    public function __construct(
        private readonly TeamQuery $team,
    ) {
    }

    #[Route(
        path: ['fr' => '/equipe', 'en' => '/team'],
        name: 'team',
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        return $this->render('organization/team.html.twig', [
            'section' => ShellSection::Settings,
            'team' => $this->team->current() ?? throw $this->createNotFoundException(),
            // Le propriétaire ne s'invite pas : il se transmet.
            'roles' => [OrganizationRole::Admin, OrganizationRole::Member],
        ]);
    }
}
