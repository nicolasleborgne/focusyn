<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\DisableTwoFactor;

use App\Identity\Application\Exception\UserNotFound;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DisableTwoFactorHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(DisableTwoFactor $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->users->ofId($userId) ?? throw UserNotFound::withId($userId);

        $user->disableTwoFactor();
        $this->users->save($user);
    }
}
