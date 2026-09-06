<?php

declare(strict_types=1);

namespace App\Organization\Application\Command\RevokeInvitation;

final readonly class RevokeInvitation
{
    public function __construct(
        public string $invitationId,
    ) {
    }
}
