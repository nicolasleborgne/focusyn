<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\ConfirmEmailChange;

final readonly class ConfirmEmailChange
{
    public function __construct(
        public string $userId,
    ) {
    }
}
