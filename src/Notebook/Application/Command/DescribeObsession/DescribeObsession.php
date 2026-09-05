<?php

declare(strict_types=1);

namespace App\Notebook\Application\Command\DescribeObsession;

final readonly class DescribeObsession
{
    /** @param list<string> $points */
    public function __construct(
        public string $slug,
        public ?string $blurb,
        public array $points,
    ) {
    }
}
