<?php

declare(strict_types=1);

namespace App\Shared\Application\Home;

use DateTimeImmutable;

final readonly class NoteTeaser
{
    /** @param list<string> $obsessions */
    public function __construct(
        public string $title,
        public string $excerpt,
        public array $obsessions,
        public DateTimeImmutable $updatedAt,
        public int $wordCount,
    ) {
    }
}
