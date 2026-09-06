<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\ForgetDevice;

final readonly class ForgetDevice
{
    public function __construct(
        public string $endpoint,
    ) {
    }
}
