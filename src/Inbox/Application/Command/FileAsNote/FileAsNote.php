<?php

declare(strict_types=1);

namespace App\Inbox\Application\Command\FileAsNote;

final readonly class FileAsNote
{
    public function __construct(
        public string $captureId,
    ) {
    }
}
