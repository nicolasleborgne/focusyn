<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\CancelEmailChange;

use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Account\CurrentAccount;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CancelEmailChangeHandler
{
    public function __construct(
        private UserRepository $users,
        private CurrentAccount $account,
    ) {
    }

    public function __invoke(CancelEmailChange $command): void
    {
        $user = $this->users->ofId(UserId::fromString(
            $this->account->idOrNull() ?? throw new InvalidArgumentException('Aucun compte connecté.'),
        ));

        if (null !== $user) {
            $user->cancelEmailChange();
            $this->users->save($user);
        }
    }
}
