<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RequestEmailChange;

final readonly class RequestEmailChange
{
    public function __construct(
        public string $email,
    ) {
    }
}
