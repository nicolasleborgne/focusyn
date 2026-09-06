<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Invitation;

use App\Organization\Application\Command\AcceptInvitation\AcceptInvitation;
use App\Organization\Application\Command\SwitchOrganization\SwitchOrganization;
use App\Organization\Application\Port\PendingInvitation;
use App\Organization\Domain\Exception\InvitationCannotBeAccepted;
use App\Shared\Application\Billing\PlanLimitReached;
use App\Shared\Application\Command\CommandBus;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Reprend l'invitation mise de côté, juste après la connexion.
 *
 * C'est ce qui évite d'avoir à recliquer le lien : on est venu pour rejoindre
 * une équipe, on y arrive.
 *
 * Un refus n'interrompt rien : on vient de se connecter, et c'est la seule
 * chose qui compte à cet instant. L'écran d'équipe dira le reste. Une équipe
 * complète est un refus comme un autre — laisser passer la limite ferait
 * échouer la connexion elle-même, ce qui serait hors de proportion.
 */
final readonly class AcceptPendingInvitationListener
{
    public function __construct(
        private CommandBus $commands,
        private PendingInvitation $pending,
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: InteractiveLoginEvent::class)]
    public function __invoke(InteractiveLoginEvent $event): void
    {
        $token = $this->pending->take();

        if (null === $token) {
            return;
        }

        try {
            $joined = $this->commands->dispatch(new AcceptInvitation($token));
            $this->commands->dispatch(new SwitchOrganization((string) $joined));
        } catch (InvitationCannotBeAccepted|PlanLimitReached $refusal) {
            $this->logger->info('Invitation refusée après connexion.', ['raison' => $refusal->getMessage()]);
        }
    }
}
