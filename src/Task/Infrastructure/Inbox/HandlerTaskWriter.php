<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Inbox;

use App\Shared\Application\Task\TaskWriter;
use App\Task\Application\Command\AddTask\AddTask;
use App\Task\Application\Command\AddTask\AddTaskHandler;

/**
 * Comme `HandlerNoteWriter` : appel direct du gestionnaire, pour que la tâche
 * ajoutée et la capture retirée relèvent d'une seule transaction.
 */
final readonly class HandlerTaskWriter implements TaskWriter
{
    public function __construct(
        private AddTaskHandler $addTask,
    ) {
    }

    public function add(string $taskListId, string $text): void
    {
        ($this->addTask)(new AddTask($taskListId, $text));
    }
}
