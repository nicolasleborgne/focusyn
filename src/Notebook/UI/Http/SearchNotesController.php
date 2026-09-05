<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchNotesController extends AbstractController
{
    #[Route(
        path: ['fr' => '/recherche', 'en' => '/search'],
        name: 'search',
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        return $this->render('notebook/search.html.twig', [
            'section' => ShellSection::Search,
        ]);
    }
}
