<?php

declare(strict_types=1);

namespace App\Reminder\Application\Command\RegisterDevice;

use App\Reminder\Domain\Model\PushEndpoint;
use App\Reminder\Domain\Model\PushKeys;
use App\Reminder\Domain\Model\PushSubscription;
use App\Reminder\Domain\Model\PushSubscriptionId;
use App\Reminder\Domain\Model\RecipientId;
use App\Reminder\Domain\Repository\PushSubscriptionRepository;
use App\Shared\Application\Account\CurrentAccount;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Enregistre l'appareil qui vient de s'abonner.
 *
 * Le point de réception fait l'identité : un navigateur qui renouvelle ses clés
 * garde la même adresse. On met alors l'abonnement à jour au lieu d'en créer un
 * second, sinon chaque rappel partirait en double sur le même appareil.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterDeviceHandler
{
    public function __construct(
        private PushSubscriptionRepository $subscriptions,
        private CurrentAccount $account,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterDevice $command): void
    {
        $subscriber = RecipientId::fromString(
            $this->account->idOrNull() ?? throw new InvalidArgumentException('Aucun compte connecté.'),
        );

        $endpoint = PushEndpoint::fromString($command->endpoint);
        $keys = PushKeys::of($command->publicKey, $command->authToken);
        $now = $this->clock->now();

        $known = $this->subscriptions->ofEndpoint($endpoint);

        if (null !== $known) {
            $known->seenAgain($keys, $now);
            $this->subscriptions->save($known);

            return;
        }

        $this->subscriptions->save(PushSubscription::register(
            PushSubscriptionId::generate(),
            $subscriber,
            $endpoint,
            $keys,
            $now,
        ));
    }
}
