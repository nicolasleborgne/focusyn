<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Application\Home\HomeDataProvider;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Accueil : ce qu'il y a à reprendre aujourd'hui.
 */
final class ShowHomeController extends AbstractController
{
    public function __construct(
        private readonly HomeDataProvider $home,
    ) {
    }

    #[Route(path: '/', name: 'home', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('home/index.html.twig', [
            'section' => ShellSection::Home,
            'home' => $this->home->forCurrentUser(),
        ]);
    }
}
