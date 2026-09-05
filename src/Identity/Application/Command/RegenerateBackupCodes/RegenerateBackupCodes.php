<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RegenerateBackupCodes;

final readonly class RegenerateBackupCodes
{
    public function __construct(
        public string $userId,
    ) {
    }
}
