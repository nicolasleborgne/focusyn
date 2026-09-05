<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\DisableTwoFactor;

final readonly class DisableTwoFactor
{
    public function __construct(
        public string $userId,
    ) {
    }
}
