<?php

declare(strict_types=1);

namespace App\Billing\Application\Command\ApplyProviderEvent;

use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Model\Subscription;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Shared\Domain\TenantId;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use ValueError;

/**
 * Applique ce que le prestataire vient de nous dire.
 *
 * Le prestataire fait foi sur l'état commercial : il connaît les prorata, les
 * relances et les cartes expirées mieux que nous. On ne recalcule rien, on
 * enregistre.
 *
 * Un événement qui ne désigne aucun abonnement connu est ignoré, pas rejeté :
 * un webhook rejoué, ou concernant un autre environnement, ne doit pas faire
 * échouer la livraison — Stripe le réessaierait indéfiniment.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApplyProviderEventHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ApplyProviderEvent $event): void
    {
        $subscription = $this->find($event);

        if (null === $subscription) {
            $this->logger->info('Événement de paiement sans abonnement correspondant.', ['type' => $event->kind]);

            return;
        }

        match ($event->kind) {
            'activated' => $this->activate($subscription, $event),
            'renewed' => $this->renew($subscription, $event),
            'payment_failed' => $subscription->paymentFailed(),
            'cancelled' => $subscription->cancel(),
            default => $this->logger->info('Événement de paiement ignoré.', ['type' => $event->kind]),
        };

        if (null !== $event->customerReference) {
            $subscription->linkTo($event->customerReference, $event->subscriptionReference);
        }

        $this->subscriptions->save($subscription);
    }

    private function activate(Subscription $subscription, ApplyProviderEvent $event): void
    {
        $plan = $this->planOf($event);

        if (null === $plan || null === $event->periodEndsAt) {
            $this->logger->warning('Activation sans palier ni échéance : ignorée.', ['type' => $event->kind]);

            return;
        }

        $subscription->activate($plan, $event->periodEndsAt, $event->seats);
    }

    private function renew(Subscription $subscription, ApplyProviderEvent $event): void
    {
        if (null !== $event->periodEndsAt) {
            $subscription->renewUntil($event->periodEndsAt);
        }
    }

    private function planOf(ApplyProviderEvent $event): ?Plan
    {
        try {
            return null === $event->plan ? null : Plan::from($event->plan);
        } catch (ValueError) {
            return null;
        }
    }

    /**
     * Trois chemins pour retrouver l'abonnement, du plus sûr au plus large :
     * l'organisation portée par les métadonnées, la référence d'abonnement,
     * puis celle du client.
     */
    private function find(ApplyProviderEvent $event): ?Subscription
    {
        if (null !== $event->organizationId) {
            try {
                $found = $this->subscriptions->ofOrganization(TenantId::fromString($event->organizationId));
            } catch (InvalidArgumentException) {
                $found = null;
            }

            if (null !== $found) {
                return $found;
            }
        }

        if (null !== $event->subscriptionReference) {
            $found = $this->subscriptions->ofSubscriptionReference($event->subscriptionReference);

            if (null !== $found) {
                return $found;
            }
        }

        return null === $event->customerReference
            ? null
            : $this->subscriptions->ofCustomerReference($event->customerReference);
    }
}
