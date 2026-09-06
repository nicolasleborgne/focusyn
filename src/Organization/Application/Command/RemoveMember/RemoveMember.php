<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\RemoveMember;

final readonly class RemoveMember
{
    public function __construct(
        public string $memberId,
    ) {
    }
}
