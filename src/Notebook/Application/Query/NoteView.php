<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

use DateTimeImmutable;

final readonly class NoteView
{
    /** @param list<string> $obsessions */
    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public array $obsessions,
        public DateTimeImmutable $updatedAt,
        public int $wordCount,
        public int $readingMinutes,
    ) {
    }
}
