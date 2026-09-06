<?php

declare(strict_types=1);

namespace App\Tests\Unit\Routine\Domain;

use App\Routine\Domain\Model\Cadence;
use App\Routine\Domain\Model\Routine;
use App\Routine\Domain\Model\RoutineId;
use App\Routine\Domain\Model\RoutineItemId;
use App\Routine\Domain\Model\RoutineName;
use App\Routine\Domain\Model\RoutineText;
use App\Shared\Domain\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Une routine, c'est une cadence et des choses à refaire.
 *
 * Deux règles font tout le reste : ce qui est **dû aujourd'hui**, et la
 * **période** dans laquelle un cochage compte. La seconde est ce qui distingue
 * une routine d'une liste de tâches — cocher n'efface rien, cela vaut jusqu'à
 * la période suivante, où tout se repose.
 */
#[CoversClass(Routine::class)]
#[CoversClass(Cadence::class)]
final class RoutineTest extends TestCase
{
    /** Le 6 septembre 2026 est un dimanche ; le 7 un lundi. */
    private const string SUNDAY = '2026-09-06 08:00';
    private const string MONDAY = '2026-09-07 08:00';

    public function testADailyRoutineOwesEverythingEveryDay(): void
    {
        $routine = $this->routine(Cadence::Daily);
        $routine->addItem(RoutineItemId::generate(), RoutineText::fromString('Lire vingt minutes'), $this->at(self::MONDAY));
        $routine->addItem(RoutineItemId::generate(), RoutineText::fromString('Cinq minutes de silence'), $this->at(self::MONDAY));

        self::assertCount(2, $routine->dueOn($this->at(self::MONDAY)));
        self::assertCount(2, $routine->dueOn($this->at(self::SUNDAY)));
    }

    public function testAWeeklyItemIsOwedOnTheDaysItNames(): void
    {
        $routine = $this->routine(Cadence::Weekly);
        $item = $this->add($routine, 'Trier la boîte de réception');
        $routine->scheduleItem($item, days: [2, 5], rank: null);

        // Mardi et vendredi : ni le lundi, ni le dimanche.
        self::assertCount(0, $routine->dueOn($this->at(self::MONDAY)));
        self::assertCount(1, $routine->dueOn($this->at('2026-09-08 08:00')));
        self::assertCount(1, $routine->dueOn($this->at('2026-09-11 08:00')));
        self::assertCount(0, $routine->dueOn($this->at(self::SUNDAY)));
    }

    public function testAWeeklyItemWithoutADayIsOwedAnyDayOfItsWeek(): void
    {
        $routine = $this->routine(Cadence::Weekly);
        $this->add($routine, 'Relire les notes de la semaine');

        // « Une fois cette semaine, quand on veut » : la période, elle, reste
        // la semaine — le cocher lundi le laisse coché jusqu'à dimanche.
        self::assertCount(1, $routine->dueOn($this->at(self::MONDAY)));
        self::assertCount(1, $routine->dueOn($this->at(self::SUNDAY)));
    }

    #[DataProvider('monthlyCases')]
    public function testAMonthlyItemFollowsItsRankAndItsDay(int $rank, string $date, bool $expected): void
    {
        $routine = $this->routine(Cadence::Monthly);
        $item = $this->add($routine, 'Tension des rayons');
        // Tous les samedis de septembre 2026 : 5, 12, 19, 26.
        $routine->scheduleItem($item, days: [6], rank: $rank);

        self::assertSame($expected, [] !== $routine->dueOn($this->at($date)));
    }

    /** @return iterable<string, array{int, string, bool}> */
    public static function monthlyCases(): iterable
    {
        yield 'le premier samedi, rang 1' => [1, '2026-09-05 08:00', true];
        yield 'le deuxième samedi, rang 1' => [1, '2026-09-12 08:00', false];
        yield 'le deuxième samedi, rang 2' => [2, '2026-09-12 08:00', true];
        yield 'le troisième samedi, rang 3' => [3, '2026-09-19 08:00', true];
        // Le dernier samedi de septembre 2026 est le 26 : c'est le quatrième,
        // et c'est aussi le dernier. Les deux réponses sont vraies.
        yield 'le dernier samedi, rang dernier' => [-1, '2026-09-26 08:00', true];
        yield 'l\'avant-dernier samedi, rang dernier' => [-1, '2026-09-19 08:00', false];
        // Un dimanche ne doit rien devoir, quel que soit le rang.
        yield 'le bon rang mais le mauvais jour' => [1, '2026-09-06 08:00', false];
    }

    public function testTheLastRankFindsTheLastOccurrenceEvenInAFiveWeekMonth(): void
    {
        $routine = $this->routine(Cadence::Monthly);
        $item = $this->add($routine, 'Chaîne et transmission');
        $routine->scheduleItem($item, days: [5], rank: -1);

        // Mai 2026 compte cinq vendredis : 1, 8, 15, 22, 29. « Dernier » doit
        // désigner le 29, pas le quatrième.
        self::assertCount(0, $routine->dueOn($this->at('2026-05-22 08:00')));
        self::assertCount(1, $routine->dueOn($this->at('2026-05-29 08:00')));
    }

    public function testTickingHoldsUntilThePeriodTurns(): void
    {
        $routine = $this->routine(Cadence::Daily);
        $item = $this->add($routine, 'Lire vingt minutes');

        $routine->tick($item, $this->at(self::MONDAY));

        self::assertTrue($routine->isTicked($item, $this->at('2026-09-07 22:00')));
        // Le lendemain, tout se repose : c'est ce qui distingue une routine
        // d'une liste, où une tâche cochée le reste.
        self::assertFalse($routine->isTicked($item, $this->at('2026-09-08 08:00')));
    }

    public function testAWeeklyTickHoldsForTheWholeWeekAndNotBeyond(): void
    {
        $routine = $this->routine(Cadence::Weekly);
        $item = $this->add($routine, 'Relire les notes de la semaine');

        $routine->tick($item, $this->at(self::MONDAY));

        // La semaine court du lundi au dimanche.
        self::assertTrue($routine->isTicked($item, $this->at('2026-09-13 20:00')));
        self::assertFalse($routine->isTicked($item, $this->at('2026-09-14 08:00')));
        // Et le dimanche d'avant appartient à la semaine précédente.
        self::assertFalse($routine->isTicked($item, $this->at(self::SUNDAY)));
    }

    public function testTickingTwiceUnticks(): void
    {
        $routine = $this->routine(Cadence::Daily);
        $item = $this->add($routine, 'Lire vingt minutes');

        $routine->tick($item, $this->at(self::MONDAY));
        $routine->tick($item, $this->at(self::MONDAY));

        self::assertFalse($routine->isTicked($item, $this->at(self::MONDAY)));
    }

    public function testTheStreakCountsWholePeriodsBackwards(): void
    {
        $routine = $this->routine(Cadence::Daily);
        $first = $this->add($routine, 'Lire vingt minutes');
        $second = $this->add($routine, 'Cinq minutes de silence');

        foreach (['2026-09-04', '2026-09-05', '2026-09-06'] as $day) {
            $routine->tick($first, $this->at($day.' 08:00'));
            $routine->tick($second, $this->at($day.' 08:00'));
        }

        // Trois jours pleins, jusqu'à hier. Aujourd'hui n'est pas fini : on ne
        // le compte pas encore, sinon la série tomberait à zéro chaque matin.
        self::assertSame(3, $routine->streakOn($this->at(self::MONDAY)));
    }

    public function testAHalfDoneDayBreaksTheStreak(): void
    {
        $routine = $this->routine(Cadence::Daily);
        $first = $this->add($routine, 'Lire vingt minutes');
        $second = $this->add($routine, 'Cinq minutes de silence');

        $routine->tick($first, $this->at('2026-09-05 08:00'));
        $routine->tick($second, $this->at('2026-09-05 08:00'));
        // Le 6, un seul des deux : la journée ne compte pas, et ce qui
        // précède ne se rattrape pas.
        $routine->tick($first, $this->at('2026-09-06 08:00'));

        self::assertSame(0, $routine->streakOn($this->at(self::MONDAY)));
    }

    public function testTodayCountsOnceItIsWhollyDone(): void
    {
        $routine = $this->routine(Cadence::Daily);
        $item = $this->add($routine, 'Lire vingt minutes');

        $routine->tick($item, $this->at(self::MONDAY));

        // Fini d'avance : la série prend le jour en cours, sans quoi cocher sa
        // routine ne changerait rien à l'écran.
        self::assertSame(1, $routine->streakOn($this->at(self::MONDAY)));
    }

    public function testARoutineWithoutAnyItemHasNoStreakToShow(): void
    {
        $routine = $this->routine(Cadence::Daily);

        // Aucune exigence, donc rien à tenir : compter des périodes vides
        // comme autant de réussites serait une flatterie.
        self::assertSame(0, $routine->streakOn($this->at(self::MONDAY)));
    }

    private function routine(Cadence $cadence): Routine
    {
        return Routine::open(
            RoutineId::generate(),
            TenantId::generate(),
            RoutineName::fromString('Matin'),
            $cadence,
            $this->at('2026-09-01 08:00'),
        );
    }

    private function add(Routine $routine, string $text): RoutineItemId
    {
        $id = RoutineItemId::generate();
        $routine->addItem($id, RoutineText::fromString($text), $this->at(self::MONDAY));

        return $id;
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment);
    }
}
