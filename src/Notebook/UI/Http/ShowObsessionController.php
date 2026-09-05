<?php

declare(strict_types=1);

namespace App\Notebook\UI\Http;

use App\Notebook\Application\Query\NotebookQuery;
use App\Shared\Application\Shell\ShellSection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowObsessionController extends AbstractController
{
    public function __construct(
        private readonly NotebookQuery $notebook,
    ) {
    }

    #[Route(
        path: '/obsessions/{slug}',
        name: 'obsession_show',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET'],
    )]
    public function __invoke(string $slug): Response
    {
        $obsession = $this->notebook->obsession($slug) ?? throw $this->createNotFoundException();

        return $this->render('notebook/obsession.html.twig', [
            'section' => ShellSection::Library,
            'obsession' => $obsession,
        ]);
    }
}
