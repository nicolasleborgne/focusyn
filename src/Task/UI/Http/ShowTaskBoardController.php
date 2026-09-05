<?php

declare(strict_types=1);

namespace App\Task\UI\Http;

use App\Shared\Application\Shell\ShellSection;
use App\Task\Application\Query\TaskBoardQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowTaskBoardController extends AbstractController
{
    public function __construct(
        private readonly TaskBoardQuery $board,
    ) {
    }

    #[Route(
        path: ['fr' => '/taches', 'en' => '/tasks'],
        name: 'tasks',
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        return $this->render('task/board.html.twig', [
            'section' => ShellSection::Tasks,
            'lists' => $this->board->board(),
        ]);
    }
}
