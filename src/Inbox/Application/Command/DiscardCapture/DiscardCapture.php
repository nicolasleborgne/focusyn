<?php

declare(strict_types=1);

namespace App\Inbox\Application\Command\DiscardCapture;

final readonly class DiscardCapture
{
    public function __construct(
        public string $captureId,
    ) {
    }
}
