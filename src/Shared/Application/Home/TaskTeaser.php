<?php

declare(strict_types=1);

namespace App\Shared\Application\Home;

final readonly class TaskTeaser
{
    public function __construct(
        /** De quoi poser un rappel dessus, sans repasser par le contexte Task. */
        public string $taskId,
        public string $text,
        public string $listName,
        public string $listSlug,
    ) {
    }
}
