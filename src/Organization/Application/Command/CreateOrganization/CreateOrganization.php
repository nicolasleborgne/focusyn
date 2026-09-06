<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\CreateOrganization;

final readonly class CreateOrganization
{
    public function __construct(
        public string $name,
    ) {
    }
}
