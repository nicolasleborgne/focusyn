<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RevokeSession;

final readonly class RevokeSession
{
    public function __construct(
        public string $sessionId,
    ) {
    }
}
