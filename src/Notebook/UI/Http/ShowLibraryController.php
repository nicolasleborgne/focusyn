<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowLibraryController extends AbstractController
{
    #[Route(
        path: ['fr' => '/bibliotheque', 'en' => '/library'],
        name: 'library',
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        return $this->render('notebook/library.html.twig', [
            'section' => ShellSection::Library,
        ]);
    }
}
