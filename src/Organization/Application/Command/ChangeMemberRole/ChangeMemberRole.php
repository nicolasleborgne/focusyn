<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\ChangeMemberRole;

final readonly class ChangeMemberRole
{
    public function __construct(
        public string $memberId,
        public string $role,
    ) {
    }
}
