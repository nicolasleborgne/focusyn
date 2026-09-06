<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command\AdjustPrivacy;

final readonly class AdjustPrivacy
{
    public function __construct(
        public string $subjectId,
        public ?string $consent = null,
        public ?bool $granted = null,
        public ?string $retention = null,
    ) {
    }
}
