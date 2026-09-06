<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RevokeSession;

use App\Identity\Application\Port\SessionCloser;
use App\Identity\Domain\Repository\LoginSessionRepository;
use App\Shared\Application\Account\CurrentAccount;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RevokeSessionHandler
{
    public function __construct(
        private LoginSessionRepository $sessions,
        private SessionCloser $closer,
        private CurrentAccount $account,
    ) {
    }

    public function __invoke(RevokeSession $command): void
    {
        $session = $this->sessions->ofId($command->sessionId);

        // Rien n'est cloisonné par organisation ici : on vérifie à la main que
        // la session appartient bien à qui demande sa fermeture.
        if (null === $session || $session->userId()->toString() !== $this->account->idOrNull()) {
            return;
        }

        $this->sessions->remove($session);
        $this->closer->close($command->sessionId);
    }
}
