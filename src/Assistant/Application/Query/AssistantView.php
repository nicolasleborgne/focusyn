<?php

declare(strict_types=1);

namespace App\Assistant\Application\Query;

use App\Assistant\Domain\Model\Provider;

final readonly class AssistantView
{
    public function __construct(
        public bool $consented,
        public Provider $provider,
        public string $model,
        public bool $hasKey,
        public ?string $baseUrl,
        public bool $wholeNote,
        public bool $usable,
    ) {
    }

    /** Le panneau ne se rend que si les deux conditions sont réunies. */
    public function isAvailable(): bool
    {
        return $this->consented && $this->usable;
    }
}
