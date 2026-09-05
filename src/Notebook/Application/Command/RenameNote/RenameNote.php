<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\RenameNote;

final readonly class RenameNote
{
    public function __construct(
        public string $noteId,
        public string $title,
    ) {
    }
}
