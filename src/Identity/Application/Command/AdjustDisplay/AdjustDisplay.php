<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\AdjustDisplay;

final readonly class AdjustDisplay
{
    public function __construct(
        public string $userId,
        public ?string $accent = null,
        public ?string $proseFont = null,
        public ?string $density = null,
        public ?float $markOpacity = null,
        public ?bool $previewPane = null,
        public ?string $theme = null,
    ) {
    }
}
