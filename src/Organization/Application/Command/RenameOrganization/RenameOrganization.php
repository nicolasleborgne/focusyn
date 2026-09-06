<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\RenameOrganization;

final readonly class RenameOrganization
{
    public function __construct(
        public string $name,
    ) {
    }
}
