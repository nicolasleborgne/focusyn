<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Query\NotebookQuery;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowLibraryController extends AbstractController
{
    public function __construct(
        private readonly NotebookQuery $notebook,
    ) {
    }

    #[Route(
        path: ['fr' => '/bibliotheque', 'en' => '/library'],
        name: 'library',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): Response
    {
        $obsession = $request->query->getString('obsession');

        return $this->render('notebook/library.html.twig', [
            'section' => ShellSection::Library,
            'notes' => '' === $obsession
                ? $this->notebook->recent()
                : $this->notebook->taggedWith($obsession),
            'obsessions' => $this->notebook->obsessions(),
            'currentObsession' => $obsession,
        ]);
    }
}
