<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RequestPasswordReset;

use App\Identity\Application\Port\PasswordResetLink;
use App\Identity\Application\Port\PasswordResetMailer;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Repository\UserRepository;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetLink $links,
        private PasswordResetMailer $mailer,
    ) {
    }

    public function __invoke(RequestPasswordReset $command): void
    {
        try {
            $email = EmailAddress::fromString($command->email);
        } catch (InvalidArgumentException) {
            return;
        }

        $user = $this->users->ofEmail($email);

        // Adresse inconnue : on ne dit rien et on ne fait rien. L'écran
        // répondra la même chose que pour un compte existant — sinon le
        // formulaire devient un moyen de savoir qui est inscrit.
        if (null === $user) {
            return;
        }

        $this->mailer->send($user, $this->links->urlFor($user));
    }
}
