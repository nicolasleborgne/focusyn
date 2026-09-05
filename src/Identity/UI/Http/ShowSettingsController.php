<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowSettingsController extends AbstractController
{
    #[Route(
        path: ['fr' => '/reglages', 'en' => '/settings'],
        name: 'settings',
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        return $this->render('identity/settings.html.twig', [
            'section' => ShellSection::Settings,
        ]);
    }
}
