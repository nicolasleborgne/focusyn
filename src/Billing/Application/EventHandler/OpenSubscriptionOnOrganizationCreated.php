<?php

declare(strict_types=1);

namespace App\Billing\Application\EventHandler;

use App\Billing\Domain\Model\Subscription;
use App\Billing\Domain\Model\SubscriptionId;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Organization\Domain\Event\OrganizationWasCreated;
use App\Shared\Domain\TenantId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Toute organisation naît avec son abonnement, en essai.
 *
 * L'événement d'un autre contexte ne transporte que des primitives : c'est le
 * seul lien entre Organization et Billing.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class OpenSubscriptionOnOrganizationCreated
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OrganizationWasCreated $event): void
    {
        $organization = TenantId::fromString($event->organizationId);

        // Rejouer un événement ne doit pas rouvrir un essai déjà consommé.
        if (null !== $this->subscriptions->ofOrganization($organization)) {
            return;
        }

        $this->subscriptions->save(Subscription::open(
            SubscriptionId::generate(),
            $organization,
            $this->clock->now(),
        ));
    }
}
