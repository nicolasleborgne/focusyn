<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\WriteNote;

final readonly class WriteNote
{
    /** @param list<string> $obsessions */
    public function __construct(
        public string $title,
        public string $body = '',
        public array $obsessions = [],
    ) {
    }
}
