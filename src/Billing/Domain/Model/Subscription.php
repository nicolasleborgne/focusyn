<?php

declare(strict_types=1);

namespace App\Billing\Domain\Model;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;

/**
 * L'abonnement d'une organisation.
 *
 * Un par organisation, y compris l'espace personnel : c'est l'organisation qui
 * délimite ce qu'on voit, et donc ce qui se facture.
 *
 * Toute la logique tient dans `entitledPlan()` : c'est la seule question que le
 * reste de l'application pose. Un essai fini, un paiement échoué, une période
 * dépassée donnent la même réponse — le gratuit. **Jamais rien de moins** : un
 * carnet ne doit pas devenir illisible parce qu'une carte a expiré.
 */
final class Subscription extends AggregateRoot
{
    private const int TRIAL_DAYS = 14;

    /**
     * Ce que l'essai ouvre en nombre de personnes.
     *
     * Assez pour essayer une équipe — inviter, accepter, partager —, trop peu
     * pour en faire tourner une gratuitement pendant quinze jours. Sans cela,
     * une organisation neuve n'aurait qu'une place et l'essai d'une équipe
     * n'aurait aucun sens.
     */
    private const int TRIAL_SEATS = 5;

    private function __construct(
        private readonly SubscriptionId $id,
        private readonly TenantId $organizationId,
        private Plan $plan,
        private SubscriptionStatus $status,
        private readonly DateTimeImmutable $trialEndsAt,
        private ?DateTimeImmutable $periodEndsAt,
        private int $seats,
        private ?string $customerReference,
        private ?string $subscriptionReference,
    ) {
    }

    /**
     * Ouvre l'abonnement d'une organisation neuve : quatorze jours d'essai, sans
     * carte, sur le palier personnel complet.
     */
    public static function open(SubscriptionId $id, TenantId $organizationId, DateTimeImmutable $now): self
    {
        return new self(
            $id,
            $organizationId,
            Plan::Free,
            SubscriptionStatus::Trialing,
            $now->modify(\sprintf('+%d days', self::TRIAL_DAYS)),
            null,
            1,
            null,
            null,
        );
    }

    public function id(): SubscriptionId
    {
        return $this->id;
    }

    public function organizationId(): TenantId
    {
        return $this->organizationId;
    }

    public function plan(): Plan
    {
        return $this->plan;
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function trialEndsAt(): DateTimeImmutable
    {
        return $this->trialEndsAt;
    }

    public function periodEndsAt(): ?DateTimeImmutable
    {
        return $this->periodEndsAt;
    }

    public function seats(): int
    {
        return $this->seats;
    }

    public function customerReference(): ?string
    {
        return $this->customerReference;
    }

    public function subscriptionReference(): ?string
    {
        return $this->subscriptionReference;
    }

    public function isTrialingAt(DateTimeImmutable $now): bool
    {
        return SubscriptionStatus::Trialing === $this->status && $now < $this->trialEndsAt;
    }

    public function isCancelled(): bool
    {
        return SubscriptionStatus::Cancelled === $this->status;
    }

    /** Un prélèvement a échoué : l'écran doit le dire, il n'y a rien d'autre à faire. */
    public function needsAttention(): bool
    {
        return SubscriptionStatus::PastDue === $this->status;
    }

    /**
     * Le palier réellement dû, à cet instant.
     *
     * L'essai donne le personnel complet ; une période payée donne son palier
     * jusqu'à son terme, résiliée ou non — ce qui est payé est payé. Tout le
     * reste retombe au gratuit.
     */
    public function entitledPlan(DateTimeImmutable $now): Plan
    {
        if ($this->isTrialingAt($now)) {
            return Plan::Personal;
        }

        $withinPaidPeriod = null !== $this->periodEndsAt && $now < $this->periodEndsAt;

        return match ($this->status) {
            SubscriptionStatus::Active,
            SubscriptionStatus::Cancelled => $withinPaidPeriod ? $this->plan : Plan::Free,
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Trialing => Plan::Free,
        };
    }

    /**
     * Combien de personnes tiennent dans l'organisation, à cet instant.
     *
     * Le plafond suit le palier réellement dû, et donc les places payées ; en
     * essai, il vaut ce que l'essai ouvre. Il arrête l'invitation, jamais
     * l'appartenance : une équipe qui repasse sous le nombre de ses membres ne
     * renvoie personne.
     */
    public function memberAllowance(DateTimeImmutable $now): int
    {
        if ($this->isTrialingAt($now)) {
            return self::TRIAL_SEATS;
        }

        return $this->entitledPlan($now)->memberAllowance($this->seats);
    }

    public function activate(Plan $plan, DateTimeImmutable $periodEndsAt, int $seats): void
    {
        $this->plan = $plan;
        $this->status = SubscriptionStatus::Active;
        $this->periodEndsAt = $periodEndsAt;
        $this->seats = max(1, $seats);
    }

    public function renewUntil(DateTimeImmutable $periodEndsAt): void
    {
        $this->status = SubscriptionStatus::Active;
        $this->periodEndsAt = $periodEndsAt;
    }

    public function paymentFailed(): void
    {
        $this->status = SubscriptionStatus::PastDue;
    }

    public function cancel(): void
    {
        $this->status = SubscriptionStatus::Cancelled;
    }

    /** Les références du prestataire de paiement, retenues telles quelles. */
    public function linkTo(string $customerReference, ?string $subscriptionReference): void
    {
        $this->customerReference = $customerReference;
        $this->subscriptionReference = $subscriptionReference;
    }
}
