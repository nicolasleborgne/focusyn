<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing\Domain;

use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Model\Subscription;
use App\Billing\Domain\Model\SubscriptionId;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Ce qu'un abonnement autorise, et à quel moment.
 *
 * Tout tient dans `entitledPlan()` : c'est la seule question que le reste de
 * l'application pose, et la seule réponse qui vaille.
 */
#[CoversClass(Subscription::class)]
#[CoversClass(Plan::class)]
final class SubscriptionTest extends TestCase
{
    public function testAnEssaiOpensOnTheFullPersonalPlan(): void
    {
        $subscription = $this->opened();

        self::assertSame(Plan::Personal, $subscription->entitledPlan(new DateTimeImmutable('2026-09-10')));
        self::assertTrue($subscription->isTrialingAt(new DateTimeImmutable('2026-09-10')));
        self::assertSame('2026-09-20', $subscription->trialEndsAt()->format('Y-m-d'));
    }

    public function testAnEssaiThatRunsOutFallsBackToTheFreePlan(): void
    {
        $subscription = $this->opened();
        $after = new DateTimeImmutable('2026-09-21');

        // Rien n'est perdu : on retombe sur le gratuit, on ne ferme pas la
        // porte.
        self::assertSame(Plan::Free, $subscription->entitledPlan($after));
        self::assertFalse($subscription->isTrialingAt($after));
    }

    public function testAnActivatedSubscriptionGivesItsPlanUntilThePeriodEnds(): void
    {
        $subscription = $this->opened();
        $subscription->activate(Plan::Team, new DateTimeImmutable('2026-10-06'), seats: 4);

        self::assertSame(Plan::Team, $subscription->entitledPlan(new DateTimeImmutable('2026-10-01')));
        self::assertSame(4, $subscription->seats());

        // Le renouvellement n'est pas arrivé : on retombe sur le gratuit plutôt
        // que de continuer à servir un palier qui n'est plus payé.
        self::assertSame(Plan::Free, $subscription->entitledPlan(new DateTimeImmutable('2026-10-07')));
    }

    public function testRenewingPushesTheHorizon(): void
    {
        $subscription = $this->opened();
        $subscription->activate(Plan::Personal, new DateTimeImmutable('2026-10-06'), seats: 1);

        $subscription->renewUntil(new DateTimeImmutable('2026-11-06'));

        self::assertSame(Plan::Personal, $subscription->entitledPlan(new DateTimeImmutable('2026-10-20')));
    }

    public function testAFailedPaymentFallsBackToTheFreePlanWithoutLosingAnything(): void
    {
        $subscription = $this->opened();
        $subscription->activate(Plan::Personal, new DateTimeImmutable('2026-10-06'), seats: 1);

        $subscription->paymentFailed();

        self::assertSame(Plan::Free, $subscription->entitledPlan(new DateTimeImmutable('2026-10-01')));
        self::assertTrue($subscription->needsAttention());
    }

    public function testCancellingLetsThePaidPeriodRunToItsEnd(): void
    {
        $subscription = $this->opened();
        $subscription->activate(Plan::Personal, new DateTimeImmutable('2026-10-06'), seats: 1);

        $subscription->cancel();

        // Ce qui est payé est payé : on ne coupe pas au milieu du mois.
        self::assertSame(Plan::Personal, $subscription->entitledPlan(new DateTimeImmutable('2026-10-01')));
        self::assertSame(Plan::Free, $subscription->entitledPlan(new DateTimeImmutable('2026-10-07')));
        self::assertTrue($subscription->isCancelled());
    }

    public function testTheFreePlanCountsNotesAndClosesTheRest(): void
    {
        self::assertSame(50, Plan::Free->noteAllowance());
        self::assertFalse(Plan::Free->allowsAssistant());
        self::assertFalse(Plan::Free->allowsTeams());
    }

    public function testAPaidPlanCountsNothingAndOpensEverything(): void
    {
        foreach ([Plan::Personal, Plan::Team] as $plan) {
            self::assertNull($plan->noteAllowance(), $plan->value);
            self::assertTrue($plan->allowsAssistant(), $plan->value);
            self::assertTrue($plan->allowsTeams(), $plan->value);
        }
    }

    public function testOnlyTheTeamPlanIsBilledPerSeat(): void
    {
        self::assertTrue(Plan::Team->isBilledPerSeat());
        self::assertFalse(Plan::Personal->isBilledPerSeat());
        self::assertFalse(Plan::Free->isBilledPerSeat());
    }

    public function testTheTrialOpensASmallTeamRatherThanASingleSeat(): void
    {
        $subscription = $this->opened();

        // L'essai ouvre l'équipe : sans cela, une organisation créée pour
        // l'essayer ne pourrait inviter personne, et l'essai ne servirait à
        // rien. Cinq places, pas davantage — on essaie une équipe, on ne la
        // fait pas tourner gratuitement quinze jours.
        self::assertSame(5, $subscription->memberAllowance(new DateTimeImmutable('2026-09-10')));
    }

    public function testATeamHasExactlyTheSeatsItPaysFor(): void
    {
        $subscription = $this->opened();
        $subscription->activate(Plan::Team, new DateTimeImmutable('2026-10-06'), seats: 3);

        self::assertSame(3, $subscription->memberAllowance(new DateTimeImmutable('2026-10-01')));
    }

    public function testThePersonalPlanIsForOnePerson(): void
    {
        $subscription = $this->opened();
        $subscription->activate(Plan::Personal, new DateTimeImmutable('2026-10-06'), seats: 9);

        // Le personnel est à prix fixe : les places déclarées par le
        // prestataire ne l'agrandissent pas, sans quoi on tiendrait une équipe
        // au prix d'une personne.
        self::assertSame(1, $subscription->memberAllowance(new DateTimeImmutable('2026-10-01')));
    }

    public function testOnceEverythingLapsesOnlyOnePlaceRemains(): void
    {
        $subscription = $this->opened();
        $subscription->activate(Plan::Team, new DateTimeImmutable('2026-10-06'), seats: 8);

        // La période est passée : le plafond retombe avec le palier. Personne
        // n'est pour autant renvoyé — c'est l'invitation qui s'arrête, pas
        // l'appartenance.
        self::assertSame(1, $subscription->memberAllowance(new DateTimeImmutable('2026-10-07')));
    }

    public function testTheSeatsOnlyCountOnThePlanBilledPerSeat(): void
    {
        self::assertSame(4, Plan::Team->memberAllowance(4));
        self::assertSame(1, Plan::Personal->memberAllowance(4));
        self::assertSame(1, Plan::Free->memberAllowance(4));

        // Une organisation sans membre n'existe pas : jamais zéro place.
        self::assertSame(1, Plan::Team->memberAllowance(0));
    }

    private function opened(): Subscription
    {
        return Subscription::open(
            SubscriptionId::generate(),
            TenantId::generate(),
            new DateTimeImmutable('2026-09-06'),
        );
    }
}
