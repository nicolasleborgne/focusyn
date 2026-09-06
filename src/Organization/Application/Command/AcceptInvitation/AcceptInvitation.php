<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\AcceptInvitation;

final readonly class AcceptInvitation
{
    public function __construct(
        public string $token,
    ) {
    }
}
