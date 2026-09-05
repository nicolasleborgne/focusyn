<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\Model\User;

interface PasswordResetMailer
{
    public function send(User $user, string $url): void;
}
