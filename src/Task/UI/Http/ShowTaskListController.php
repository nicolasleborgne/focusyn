<?php

declare(strict_types=1);

namespace App\Task\UI\Http;

use App\Shared\Application\Shell\ShellSection;
use App\Task\Application\Query\TaskBoardQuery;
use App\Task\Domain\Model\TaskListId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ShowTaskListController extends AbstractController
{
    public function __construct(
        private readonly TaskBoardQuery $board,
    ) {
    }

    #[Route(
        path: '/taches/{id}',
        name: 'task_list_show',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function __invoke(string $id): Response
    {
        $list = $this->board->list(TaskListId::fromString($id))
            ?? throw $this->createNotFoundException();

        return $this->render('task/list.html.twig', [
            'section' => ShellSection::Tasks,
            'list' => $list,
            'focusable' => true,
        ]);
    }
}
