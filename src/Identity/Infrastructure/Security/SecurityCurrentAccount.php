<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Application\Account\CurrentAccount;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecurityCurrentAccount implements CurrentAccount
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->security->getUser() instanceof SecurityUser;
    }

    public function emailOrNull(): ?string
    {
        return $this->account()?->getUserIdentifier();
    }

    public function idOrNull(): ?string
    {
        return $this->account()?->id();
    }

    private function account(): ?SecurityUser
    {
        $user = $this->security->getUser();

        return $user instanceof SecurityUser ? $user : null;
    }
}
