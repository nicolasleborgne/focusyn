<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\SessionStarter;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecuritySessionStarter implements SessionStarter
{
    public function __construct(
        private UserRepository $users,
        private Security $security,
    ) {
    }

    public function signIn(UserId $userId): void
    {
        $user = $this->users->ofId($userId);

        if (null === $user) {
            return;
        }

        $this->security->login(SecurityUser::fromDomain($user));
    }
}
