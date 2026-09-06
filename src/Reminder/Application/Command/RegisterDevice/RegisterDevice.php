<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\RegisterDevice;

final readonly class RegisterDevice
{
    public function __construct(
        public string $endpoint,
        public string $publicKey,
        public string $authToken,
    ) {
    }
}
