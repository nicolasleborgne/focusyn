<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\SwitchOrganization;

final readonly class SwitchOrganization
{
    public function __construct(
        public string $organizationId,
    ) {
    }
}
