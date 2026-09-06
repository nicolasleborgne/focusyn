<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\User;

interface EmailChangeLink
{
    public function urlFor(User $user): string;

    public function isValidFor(User $user, string $uri): bool;
}
