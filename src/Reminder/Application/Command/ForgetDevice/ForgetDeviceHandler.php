<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\ForgetDevice;

use App\Reminder\Domain\Model\PushEndpoint;
use App\Reminder\Domain\Repository\PushSubscriptionRepository;
use App\Shared\Application\Account\CurrentAccount;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ForgetDeviceHandler
{
    public function __construct(
        private PushSubscriptionRepository $subscriptions,
        private CurrentAccount $account,
    ) {
    }

    public function __invoke(ForgetDevice $command): void
    {
        $subscription = $this->subscriptions->ofEndpoint(PushEndpoint::fromString($command->endpoint));

        // Rien n'est cloisonné par organisation ici : on vérifie donc à la main
        // que l'appareil est bien celui de la personne qui demande son retrait.
        if (null !== $subscription && $subscription->subscriberId()->toString() === $this->account->idOrNull()) {
            $this->subscriptions->remove($subscription);
        }
    }
}
