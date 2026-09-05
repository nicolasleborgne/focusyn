<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchNotesController extends AbstractController
{
    #[Route(
        path: ['fr' => '/recherche', 'en' => '/search'],
        name: 'search',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): Response
    {
        return $this->render('notebook/search.html.twig', [
            'section' => ShellSection::Search,
            // La requête initiale vient de l'URL : une recherche doit pouvoir
            // se partager par un lien, et le composant reprend la main ensuite.
            'query' => $request->query->getString('query'),
        ]);
    }
}
