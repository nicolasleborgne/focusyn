<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\DropReminder;

final readonly class DropReminder
{
    public function __construct(
        public string $subject,
    ) {
    }
}
