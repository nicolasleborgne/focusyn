<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

final readonly class ObsessionPageView
{
    /**
     * @param list<string>      $points
     * @param list<NoteSummary> $notes
     */
    public function __construct(
        public string $name,
        public string $slug,
        public int $noteCount,
        public ?string $blurb,
        public array $points,
        public array $notes,
    ) {
    }

    public function saysSomething(): bool
    {
        return null !== $this->blurb || [] !== $this->points;
    }
}
