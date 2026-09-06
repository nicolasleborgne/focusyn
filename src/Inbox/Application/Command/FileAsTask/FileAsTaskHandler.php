<?php

declare(strict_types=1);

namespace App\Inbox\Application\Command\FileAsTask;

use App\Inbox\Application\Exception\CaptureNotFound;
use App\Inbox\Domain\Model\CaptureId;
use App\Inbox\Domain\Repository\CaptureRepository;
use App\Shared\Application\Task\TaskWriter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * La capture devient une tâche dans la liste désignée.
 *
 * C'est le titre qui passe, pas le texte entier : une tâche se lit d'une ligne.
 * Ce qui dépassait est perdu — d'où le choix, à l'écran, de proposer « en note »
 * d'abord pour ce qui a de la matière.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class FileAsTaskHandler
{
    public function __construct(
        private CaptureRepository $captures,
        private TaskWriter $tasks,
    ) {
    }

    public function __invoke(FileAsTask $command): void
    {
        $capture = $this->captures->ofId(CaptureId::fromString($command->captureId))
            ?? throw CaptureNotFound::withId($command->captureId);

        $this->tasks->add($command->taskListId, $capture->title()->toString());

        $this->captures->remove($capture);
    }
}
