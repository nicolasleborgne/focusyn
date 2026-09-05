<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\SetNoteObsessions;

final readonly class SetNoteObsessions
{
    /** @param list<string> $obsessions */
    public function __construct(
        public string $noteId,
        public array $obsessions,
    ) {
    }
}
