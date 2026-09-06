<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command\AdjustAssistant;

final readonly class AdjustAssistant
{
    public function __construct(
        public ?string $provider = null,
        public ?string $apiKey = null,
        public ?string $model = null,
        public ?string $baseUrl = null,
        public ?bool $wholeNote = null,
    ) {
    }
}
