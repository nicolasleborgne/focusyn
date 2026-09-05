<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\ResetPassword;

use App\Identity\Application\Exception\UserNotFound;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ResetPasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
    ) {
    }

    public function __invoke(ResetPassword $command): void
    {
        $userId = UserId::fromString($command->userId);
        $user = $this->users->ofId($userId) ?? throw UserNotFound::withId($userId);

        $user->changePassword($this->passwords->hash($command->plainPassword));
        $this->users->save($user);
    }
}
