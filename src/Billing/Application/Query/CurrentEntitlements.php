<?php

declare(strict_types=1);

namespace App\Billing\Application\Query;

use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\SubscriptionRepository;
use App\Shared\Application\Billing\Entitlements;
use App\Shared\Application\Tenant\CurrentTenant;
use Psr\Clock\ClockInterface;

/**
 * Les droits de l'organisation courante.
 *
 * Le palier est résolu une fois par requête : la question est posée plusieurs
 * fois par écran, et la réponse ne change pas entre deux d'entre elles.
 *
 * Hors organisation — un visiteur, un worker — c'est le gratuit qui s'applique :
 * l'inconnu n'ouvre aucune porte.
 */
final class CurrentEntitlements implements Entitlements
{
    private ?Plan $resolved = null;

    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly CurrentTenant $tenant,
        private readonly ClockInterface $clock,
    ) {
    }

    public function allowsAssistant(): bool
    {
        return $this->plan()->allowsAssistant();
    }

    public function allowsTeams(): bool
    {
        return $this->plan()->allowsTeams();
    }

    public function noteAllowance(): ?int
    {
        return $this->plan()->noteAllowance();
    }

    public function plan(): Plan
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        $organization = $this->tenant->idOrNull();

        if (null === $organization) {
            return $this->resolved = Plan::Free;
        }

        $subscription = $this->subscriptions->ofOrganization($organization);

        return $this->resolved = $subscription?->entitledPlan($this->clock->now()) ?? Plan::Free;
    }
}
