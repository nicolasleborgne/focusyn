<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\ConfirmEmailChange;

use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ConfirmEmailChangeHandler
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function __invoke(ConfirmEmailChange $command): void
    {
        $user = $this->users->ofId(UserId::fromString($command->userId))
            ?? throw new InvalidArgumentException('Compte introuvable.');

        $pending = $user->pendingEmail()
            ?? throw new InvalidArgumentException('Aucun changement d\'adresse n\'attend cette confirmation.');

        // L'adresse a pu être prise entre la demande et la confirmation : on
        // revérifie au dernier moment, la contrainte d'unicité de la base ne
        // dirait rien de lisible.
        if (null !== $this->users->ofEmail($pending)) {
            $user->cancelEmailChange();
            $this->users->save($user);

            throw EmailAlreadyRegistered::for($pending);
        }

        $user->confirmEmailChange($pending);
        $this->users->save($user);
    }
}
