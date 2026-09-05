<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\DeleteNote;

final readonly class DeleteNote
{
    public function __construct(
        public string $noteId,
    ) {
    }
}
