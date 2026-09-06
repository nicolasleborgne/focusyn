<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Model\Retention;

final readonly class PrivacyView
{
    /**
     * @param array<string, bool> $consents
     * @param array<string, int>  $summary
     */
    public function __construct(
        public array $consents,
        public Retention $retention,
        public array $summary,
    ) {
    }

    public function allows(Consent $consent): bool
    {
        return $this->consents[$consent->value] ?? false;
    }
}
