<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\InviteMember;

final readonly class InviteMember
{
    public function __construct(
        public string $email,
        public string $role,
    ) {
    }
}
