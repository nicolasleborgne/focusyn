<?php

declare(strict_types=1);

namespace App\Identity\Application\Query;

use App\Identity\Application\Exception\UserNotFound;
use App\Identity\Domain\Model\LoginSession;
use App\Identity\Domain\Model\OAuthIdentity;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\LoginSessionRepository;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AccountSettingsQuery
{
    public function __construct(
        private UserRepository $users,
        private LoginSessionRepository $sessions,
        private RequestStack $requests,
    ) {
    }

    public function forUser(UserId $userId): AccountSettings
    {
        $user = $this->users->ofId($userId) ?? throw UserNotFound::withId($userId);

        return new AccountSettings(
            email: $user->email()->toString(),
            pendingEmail: $user->pendingEmail()?->toString(),
            twoFactorEnabled: $user->hasTwoFactorEnabled(),
            remainingBackupCodes: \count($user->backupCodes()),
            linkedProviders: array_map(
                static fn (OAuthIdentity $identity): string => $identity->provider()->value,
                $user->oauthIdentities(),
            ),
            sessions: $this->sessionsOf($userId),
        );
    }

    /** @return list<SessionEntry> */
    private function sessionsOf(UserId $userId): array
    {
        $current = $this->requests->getSession()->getId();

        return array_map(
            static fn (LoginSession $session): SessionEntry => new SessionEntry(
                id: $session->id(),
                device: $session->device()->toString(),
                lastSeenAt: $session->lastSeenAt(),
                current: $session->id() === $current,
            ),
            $this->sessions->ofUser($userId),
        );
    }
}
