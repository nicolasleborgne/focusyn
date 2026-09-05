<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

use App\Identity\Application\Exception\UserNotFound;
use App\Identity\Domain\Model\OAuthIdentity;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;

final readonly class AccountSettingsQuery
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function forUser(UserId $userId): AccountSettings
    {
        $user = $this->users->ofId($userId) ?? throw UserNotFound::withId($userId);

        return new AccountSettings(
            email: $user->email()->toString(),
            twoFactorEnabled: $user->hasTwoFactorEnabled(),
            remainingBackupCodes: \count($user->backupCodes()),
            linkedProviders: array_map(
                static fn (OAuthIdentity $identity): string => $identity->provider()->value,
                $user->oauthIdentities(),
            ),
        );
    }
}
