<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\RequestEmailChange;

use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Port\EmailChangeLink;
use App\Identity\Application\Port\EmailChangeMailer;
use App\Identity\Domain\Model\EmailAddress;
use App\Identity\Domain\Model\UserId;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Account\CurrentAccount;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Demande à changer d'adresse de connexion.
 *
 * Rien ne change tout de suite : la nouvelle adresse attend d'être confirmée
 * depuis sa propre boîte aux lettres. Une faute de frappe fermerait sinon le
 * compte à son propriétaire.
 *
 * Deux courriels partent : le lien vers la nouvelle adresse, et un
 * avertissement vers l'ancienne — c'est le seul moyen pour le propriétaire
 * légitime d'apprendre qu'on essaie de déplacer son compte.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RequestEmailChangeHandler
{
    public function __construct(
        private UserRepository $users,
        private EmailChangeLink $links,
        private EmailChangeMailer $mailer,
        private CurrentAccount $account,
    ) {
    }

    public function __invoke(RequestEmailChange $command): void
    {
        $user = $this->users->ofId(UserId::fromString(
            $this->account->idOrNull() ?? throw new InvalidArgumentException('Aucun compte connecté.'),
        )) ?? throw new InvalidArgumentException('Compte introuvable.');

        $email = EmailAddress::fromString($command->email);

        if (null !== $this->users->ofEmail($email)) {
            throw EmailAlreadyRegistered::for($email);
        }

        $previous = $user->email()->toString();

        $user->requestEmailChange($email);
        $this->users->save($user);

        $this->mailer->sendConfirmation($email->toString(), $this->links->urlFor($user));
        $this->mailer->warnPreviousAddress($previous, $email->toString());
    }
}
