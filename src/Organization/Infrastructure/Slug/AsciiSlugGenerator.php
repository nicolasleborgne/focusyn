<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Slug;

use App\Organization\Application\Port\SlugGenerator;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class AsciiSlugGenerator implements SlugGenerator
{
    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function slugify(string $text): string
    {
        return $this->slugger->slug($text)->lower()->toString();
    }
}
