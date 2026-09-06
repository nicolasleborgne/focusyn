<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command\AskAssistant;

final readonly class AskAssistant
{
    public function __construct(
        public string $noteId,
        public string $instruction,
        public string $selection = '',
    ) {
    }
}
