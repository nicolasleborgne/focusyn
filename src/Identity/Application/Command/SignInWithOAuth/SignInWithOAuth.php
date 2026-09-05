<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\SignInWithOAuth;

final readonly class SignInWithOAuth
{
    public function __construct(
        public string $provider,
        public string $externalId,
        public string $email,
        public bool $emailVerified,
    ) {
    }
}
