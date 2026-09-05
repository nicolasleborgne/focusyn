<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\UnlinkOAuthProvider;

use App\Identity\Application\Exception\UserNotFound;
use App\Identity\Domain\Model\OAuthProvider;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UnlinkOAuthProviderHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(UnlinkOAuthProvider $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->users->ofId($userId) ?? throw UserNotFound::withId($userId);

        $user->unlinkOAuthIdentity(OAuthProvider::from($command->provider));
        $this->users->save($user);
    }
}
