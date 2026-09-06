<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain;

use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Model\PrivacyChoices;
use App\Privacy\Domain\Model\Retention;
use App\Privacy\Domain\Model\SubjectId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Les choix d'une personne sur ses données.
 *
 * Ils appartiennent à la personne, pas à l'organisation : changer d'équipe ne
 * remet pas un consentement.
 */
#[CoversClass(PrivacyChoices::class)]
final class PrivacyChoicesTest extends TestCase
{
    private const string AT = '2026-09-05 10:00:00';
    private const string LATER = '2026-09-06 11:00:00';

    public function testNothingIsConsentedToByDefault(): void
    {
        $choices = self::choices();

        foreach (Consent::cases() as $consent) {
            self::assertFalse(
                $choices->allows($consent),
                'Un consentement se donne ; il ne se présume pas.',
            );
        }
    }

    public function testTheDefaultRetentionIsTheShortestOffered(): void
    {
        self::assertSame(
            Retention::TwelveMonths,
            self::choices()->retention(),
            'À défaut de choix, on conserve le moins longtemps possible.',
        );
    }

    public function testAConsentCanBeGivenThenWithdrawn(): void
    {
        $choices = self::choices();

        $choices->grant(Consent::Assistant, self::later());
        self::assertTrue($choices->allows(Consent::Assistant));

        $choices->withdraw(Consent::Assistant, self::later());
        self::assertFalse($choices->allows(Consent::Assistant));
    }

    public function testConsentsAreIndependent(): void
    {
        $choices = self::choices();

        $choices->grant(Consent::Usage, self::later());

        self::assertTrue($choices->allows(Consent::Usage));
        self::assertFalse($choices->allows(Consent::Assistant));
    }

    public function testGivingAConsentAlreadyGivenChangesNothing(): void
    {
        $choices = self::choices();
        $choices->grant(Consent::Assistant, new DateTimeImmutable(self::AT));

        $choices->grant(Consent::Assistant, self::later());

        self::assertSame(self::AT, $choices->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testTheRetentionCanBeChosen(): void
    {
        $choices = self::choices();

        $choices->keepFor(Retention::Unlimited, self::later());

        self::assertSame(Retention::Unlimited, $choices->retention());
        self::assertSame(self::LATER, $choices->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testItRemembersWhenEachConsentWasGiven(): void
    {
        $choices = self::choices();

        $choices->grant(Consent::Backup, self::later());

        // La date compte : c'est elle qui prouve le consentement, et sa
        // révocation.
        self::assertSame(self::LATER, $choices->grantedAt(Consent::Backup)?->format('Y-m-d H:i:s'));
        self::assertNull($choices->grantedAt(Consent::Assistant));
    }

    public function testWithdrawingForgetsTheDate(): void
    {
        $choices = self::choices();
        $choices->grant(Consent::Backup, self::later());

        $choices->withdraw(Consent::Backup, self::later());

        self::assertNull($choices->grantedAt(Consent::Backup));
    }

    public function testEachRetentionKnowsItsHorizon(): void
    {
        self::assertSame(12, Retention::TwelveMonths->months());
        self::assertSame(24, Retention::TwentyFourMonths->months());
        self::assertNull(Retention::Unlimited->months());
    }

    private static function choices(): PrivacyChoices
    {
        return PrivacyChoices::forSubject(SubjectId::generate(), new DateTimeImmutable(self::AT));
    }

    private static function later(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::LATER);
    }
}
