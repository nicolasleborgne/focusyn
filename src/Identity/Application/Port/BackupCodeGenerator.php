<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

interface BackupCodeGenerator
{
    /**
     * Codes en clair, à montrer une seule fois.
     *
     * @return list<string>
     */
    public function generate(int $count = 3): array;
}
