<?php

declare(strict_types=1);

namespace App\Billing\Application\Query;

use App\Billing\Application\Port\PaymentProvider;
use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Shared\Application\Notebook\NoteTally;
use App\Shared\Application\Team\TeamSize;
use App\Shared\Application\Tenant\CurrentTenant;
use Psr\Clock\ClockInterface;

final readonly class BillingQuery
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PaymentProvider $payments,
        private NoteTally $notes,
        private TeamSize $team,
        private CurrentTenant $tenant,
        private ClockInterface $clock,
    ) {
    }

    public function current(): ?BillingView
    {
        $organization = $this->tenant->idOrNull();

        if (null === $organization) {
            return null;
        }

        $subscription = $this->subscriptions->ofOrganization($organization);

        if (null === $subscription) {
            return null;
        }

        $now = $this->clock->now();
        $entitled = $subscription->entitledPlan($now);

        return new BillingView(
            entitled: $entitled,
            trialing: $subscription->isTrialingAt($now),
            trialEndsAt: $subscription->trialEndsAt(),
            periodEndsAt: $subscription->periodEndsAt(),
            cancelled: $subscription->isCancelled(),
            needsAttention: $subscription->needsAttention(),
            seats: $subscription->seats(),
            members: $this->team->size(),
            memberAllowance: $subscription->memberAllowance($now),
            notes: $this->notes->count(),
            noteAllowance: $entitled->noteAllowance(),
            hasCustomerAccount: null !== $subscription->customerReference(),
            paymentConfigured: $this->payments->isConfigured(),
        );
    }

    /** @return list<Plan> les paliers qu'on peut choisir */
    public function offered(): array
    {
        return [Plan::Personal, Plan::Team];
    }
}
