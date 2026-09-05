<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\UnlinkOAuthProvider;

final readonly class UnlinkOAuthProvider
{
    public function __construct(
        public string $userId,
        public string $provider,
    ) {
    }
}
