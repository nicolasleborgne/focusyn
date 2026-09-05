<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

final readonly class ProseLine
{
    /** @param list<ProseSegment> $segments */
    public function __construct(
        public string $kind,
        public string $mark,
        public array $segments,
        public string $raw,
    ) {
    }

    public function plainText(): string
    {
        return implode('', array_map(static fn (ProseSegment $segment): string => $segment->text, $this->segments));
    }
}
