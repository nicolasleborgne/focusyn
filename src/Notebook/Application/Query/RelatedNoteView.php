<?php

declare(strict_types=1);

namespace App\Notebook\Application\Query;

final readonly class RelatedNoteView
{
    /** @param list<string> $sharedWords ce qui a motivé le rapprochement */
    public function __construct(
        public string $id,
        public string $title,
        public array $sharedWords,
        public ?string $sharedObsession,
    ) {
    }
}
