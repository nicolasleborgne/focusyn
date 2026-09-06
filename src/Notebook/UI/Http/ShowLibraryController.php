<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowLibraryController extends AbstractController
{
    #[Route(
        path: ['fr' => '/bibliotheque', 'en' => '/library'],
        name: 'library',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): Response
    {
        // Le contenu est porté par un Live Component : filtrer réduit une
        // liste déjà affichée, sans recharger l'écran.
        return $this->render('notebook/library.html.twig', [
            'section' => ShellSection::Library,
            'currentObsession' => $request->query->getString('obsession'),
        ]);
    }
}
