<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

use DateTimeImmutable;

/**
 * Une note telle qu'elle apparaît dans une liste : de quoi la reconnaître et
 * la choisir, jamais son corps entier.
 */
final readonly class NoteSummary
{
    /** @param list<string> $obsessions */
    public function __construct(
        public string $id,
        public string $title,
        public string $excerpt,
        public array $obsessions,
        public DateTimeImmutable $updatedAt,
        public int $wordCount,
    ) {
    }
}
